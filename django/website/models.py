"""Page types and site settings.

There is one page tree in English. The Spanish, French, Portuguese and
Russian sites are translations of it (Wagtail "locales"): each page has a
translation per language, with its own text, title and URL, and each
domain (a Wagtail Site) shows the tree of its own language.

Each page type is one of the old sites' page templates.
"""
import logging

from django.conf import settings
from django.core.mail import EmailMessage
from django.db import models
from django.shortcuts import redirect
from django.template.response import TemplateResponse
from django.utils.translation import gettext_lazy as _
from wagtail.admin.panels import FieldPanel, MultiFieldPanel, PageChooserPanel
from wagtail.contrib.settings.models import BaseSiteSetting, register_setting
from wagtail.fields import StreamField
from wagtail.images.blocks import ImageBlock
from wagtail.models import Page
from wagtail.search import index
from wagtail.snippets.models import register_snippet

from .blocks import ContentBlock, ImageRowBlock

logger = logging.getLogger(__name__)


def content_field(**kwargs):
    return StreamField(ContentBlock(), blank=True, use_json_field=True, **kwargs)


def image_field(help_text=""):
    return models.ForeignKey(
        "wagtailimages.Image", null=True, blank=True, on_delete=models.SET_NULL,
        related_name="+", help_text=help_text,
    )


class BasePage(Page):
    """Fields every page has.

    title is the name in the menus (the old "nav title"); seo_title is the
    page title shown in the browser tab and as the heading on most pages.
    """

    meta_keywords = models.CharField(max_length=500, blank=True)

    promote_panels = [
        MultiFieldPanel(
            [
                FieldPanel("slug"),
                FieldPanel("seo_title"),
                FieldPanel("search_description"),
                FieldPanel("meta_keywords"),
            ],
            "For search engines",
        ),
        FieldPanel("show_in_menus"),
    ]

    search_fields = Page.search_fields

    class Meta:
        abstract = True

    @property
    def heading(self):
        return self.seo_title or self.title


class PageImageMixin(models.Model):
    image = image_field("The wide picture at the top of the page")
    image_alt = models.CharField("Picture description", max_length=255, blank=True)

    class Meta:
        abstract = True


class HomePage(BasePage):
    header_text = content_field()
    body = content_field()

    parent_page_types = ["wagtailcore.Page"]
    content_panels = BasePage.content_panels + [FieldPanel("header_text"), FieldPanel("body")]
    search_fields = BasePage.search_fields + [index.SearchField("body")]

    class Meta:
        verbose_name = "home page"


class StandardPage(PageImageMixin, BasePage):
    """Text with a picture at the top (old template page.php)."""

    header_text = content_field()
    body = content_field()

    content_panels = BasePage.content_panels + [
        FieldPanel("header_text"),
        MultiFieldPanel([FieldPanel("image"), FieldPanel("image_alt")], "Picture"),
        FieldPanel("body"),
    ]
    search_fields = BasePage.search_fields + [index.SearchField("body")]


class ProductPage(PageImageMixin, BasePage):
    """A product: a heading, a picture and text (old template product.php)."""

    heading_text = models.CharField("Heading", max_length=255, blank=True,
                                    help_text="Leave empty to use the title")
    body = content_field()

    content_panels = BasePage.content_panels + [
        FieldPanel("heading_text"),
        MultiFieldPanel([FieldPanel("image"), FieldPanel("image_alt")], "Picture"),
        FieldPanel("body"),
    ]
    search_fields = BasePage.search_fields + [index.SearchField("body")]


class SliderPage(BasePage):
    """Pictures that fade from one to the next, then text (old templates
    page-slider.php and page-slider-body-image.php)."""

    header_text = content_field()
    slides = StreamField([("slide", ImageBlock())], blank=True, use_json_field=True)
    side_image = image_field("Optional tall picture to the left of the text")
    body = content_field()

    content_panels = BasePage.content_panels + [
        FieldPanel("header_text"),
        FieldPanel("slides"),
        FieldPanel("side_image"),
        FieldPanel("body"),
    ]
    search_fields = BasePage.search_fields + [index.SearchField("body")]


class TwoColumnPage(BasePage):
    """Heading and two columns of text (old template page-two-column.php)."""

    left_column = content_field()
    right_column = content_field()

    content_panels = BasePage.content_panels + [FieldPanel("left_column"), FieldPanel("right_column")]
    search_fields = BasePage.search_fields + [index.SearchField("left_column"), index.SearchField("right_column")]


class StockPage(PageImageMixin, BasePage):
    """Picture, text, two columns and the stock chart (old template two-column-lower-image.php)."""

    body = content_field()
    intro = content_field()
    left_column = content_field()
    right_column = content_field()
    chart_image = image_field("The chart under the columns")

    content_panels = BasePage.content_panels + [
        MultiFieldPanel([FieldPanel("image"), FieldPanel("image_alt")], "Picture"),
        FieldPanel("body"),
        FieldPanel("intro"),
        FieldPanel("left_column"),
        FieldPanel("right_column"),
        FieldPanel("chart_image"),
    ]
    search_fields = BasePage.search_fields + [index.SearchField("body")]


class ImageColumnPage(PageImageMixin, BasePage):
    """Product page with a picture beside each paragraph (old template page-image-column.php)."""

    intro = content_field()
    advert_image = image_field("Small advert beside the introduction")
    advert_link = models.ForeignKey(
        "wagtailcore.Page", null=True, blank=True, on_delete=models.SET_NULL, related_name="+",
        help_text="Page the advert links to",
    )
    full_width_text = content_field()
    rows = StreamField([("row", ImageRowBlock())], blank=True, use_json_field=True)

    content_panels = BasePage.content_panels + [
        MultiFieldPanel([FieldPanel("image"), FieldPanel("image_alt")], "Picture"),
        FieldPanel("intro"),
        MultiFieldPanel([FieldPanel("advert_image"), PageChooserPanel("advert_link")], "Advert"),
        FieldPanel("full_width_text"),
        FieldPanel("rows"),
    ]
    search_fields = BasePage.search_fields + [index.SearchField("intro"), index.SearchField("rows")]

    # The old template nudged each row so that picture and text line up.
    ROW_STYLES = [
        ("padding-top: 30px; margin-top: 0;", "margin-top: 0;"),
        ("", "padding-top: 10px;"),
        ("padding-top: 9px;", ""),
        ("", "padding-top: 12px;"),
        ("padding-top: 25px; margin-top: 0;", "margin-top: 0;"),
    ]

    def get_context(self, request, *args, **kwargs):
        context = super().get_context(request, *args, **kwargs)
        context["styled_rows"] = [
            (row, *(self.ROW_STYLES[i] if i < len(self.ROW_STYLES) else ("", "")))
            for i, row in enumerate(self.rows)
        ]
        return context


class SizeChartPage(BasePage):
    """The BWG/SWG wall thickness tables (old template size-conversion-chart.php)."""

    intro = content_field()

    content_panels = BasePage.content_panels + [FieldPanel("intro")]


class ProductsPage(BasePage):
    """Holds the product pages, so that their addresses start with /products/.

    It has no page of its own: like on the old sites, /products goes to the
    home page.
    """

    subpage_types = ["website.ProductPage", "website.ImageColumnPage", "website.StockPage",
                     "website.StandardPage", "website.SliderPage", "website.TwoColumnPage"]

    def serve(self, request, *args, **kwargs):
        return redirect(self.get_parent().get_url(request))

    class Meta:
        verbose_name = "products folder"


class ContactPage(BasePage):
    """Map, contact details and the contact form (old template contact.php)."""

    header_text = content_field()
    map_url = models.URLField("Google Maps embed address", max_length=1000, blank=True)
    left_column = content_field()
    right_column = content_field()
    recipient = models.EmailField(
        "Send messages to", blank=True,
        help_text="Leave empty to use the email address in Settings > Site details",
    )
    email_subject = models.CharField(max_length=255, default="Contact form message from TSI Website.")

    content_panels = BasePage.content_panels + [
        FieldPanel("header_text"),
        FieldPanel("map_url"),
        FieldPanel("left_column"),
        FieldPanel("right_column"),
        MultiFieldPanel([FieldPanel("recipient"), FieldPanel("email_subject")], "Contact form"),
    ]

    def serve(self, request, *args, **kwargs):
        from .forms import ContactForm

        sent = False
        if request.method == "POST":
            form = ContactForm(request.POST)
            if form.is_valid():
                self.send(request, form.cleaned_data)
                sent = True
        else:
            form = ContactForm()
        context = self.get_context(request, *args, **kwargs)
        context.update(form=form, sent=sent)
        return TemplateResponse(request, self.get_template(request), context)

    def send(self, request, data):
        site_details = SiteDetails.for_request(request)
        recipient = self.recipient or site_details.email or settings.DEFAULT_FROM_EMAIL
        body = "".join(f"{label}: {data.get(name, '')}\n\n" for name, label in ContactSubmission.FIELDS)
        ContactSubmission.objects.create(
            site=request.get_host()[:255], recipient=recipient,
            **{name: data.get(name, "") for name, label in ContactSubmission.FIELDS},
        )
        try:
            EmailMessage(
                subject=self.email_subject,
                body=body,
                from_email=settings.DEFAULT_FROM_EMAIL,
                to=[recipient],
                reply_to=[data["email"]] if data.get("email") else None,
            ).send(fail_silently=False)
        except Exception:
            # the message is kept under Snippets > Contact form messages either way
            logger.exception("Contact form email to %s could not be sent", recipient)


@register_snippet
class ContactSubmission(models.Model):
    """A copy of every contact form message, in case an email goes missing."""

    FIELDS = [
        ("name", "Name"), ("contact", "Contact"), ("email", "Email"), ("job", "Job Description"),
        ("company", "Company Name"), ("country", "Country"), ("message", "Message"),
    ]

    created = models.DateTimeField(auto_now_add=True)
    site = models.CharField(max_length=255)
    recipient = models.EmailField()
    name = models.CharField(max_length=255)
    contact = models.CharField(max_length=255)
    email = models.CharField(max_length=255, blank=True)
    job = models.CharField(max_length=255, blank=True)
    company = models.CharField(max_length=255, blank=True)
    country = models.CharField(max_length=255, blank=True)
    message = models.TextField(blank=True)

    panels = [FieldPanel(name) for name in ["site", "name", "contact", "email", "job", "company", "country", "message"]]

    class Meta:
        ordering = ["-created"]
        verbose_name = "contact form message"

    def __str__(self):
        return f"{self.created:%Y-%m-%d %H:%M} {self.name} ({self.site})"


@register_setting(icon="site")
class SiteDetails(BaseSiteSetting):
    """Contact details and the text around every page, per site (language).

    These were written into the old sites' templates; here they can be
    edited under Settings > Site details for each domain.
    """

    telephone = models.CharField(max_length=100, blank=True)
    email = models.EmailField(blank=True)

    tagline = models.TextField(
        blank=True, help_text="Top line beside the logo. Line breaks are kept."
    )
    tagline_highlight = models.TextField(
        blank=True, help_text="Second line beside the logo, in blue. Line breaks are kept."
    )
    telephone_label = models.CharField(max_length=100, default="Call us on:")
    contact_page = models.ForeignKey("wagtailcore.Page", null=True, blank=True, on_delete=models.SET_NULL,
                                     related_name="+", help_text="The 'Contact us' button")
    emergency_page = models.ForeignKey("wagtailcore.Page", null=True, blank=True, on_delete=models.SET_NULL,
                                       related_name="+", help_text="The 'Emergency delivery' button")

    products_heading = models.CharField(max_length=100, default="Our Products")
    stock_box_page = models.ForeignKey("wagtailcore.Page", null=True, blank=True, on_delete=models.SET_NULL,
                                       related_name="+")
    stock_box_heading = models.CharField(max_length=100, blank=True)
    stock_box_text = models.TextField(blank=True, help_text="HTML")
    certificate_link = models.TextField(blank=True, help_text="HTML. Link text for the certificate; empty for none.")
    sidebar_text = models.TextField(blank=True, help_text="HTML")

    read_more = models.CharField(max_length=100, default="Read more >")
    footer_quality_page = models.ForeignKey("wagtailcore.Page", null=True, blank=True, on_delete=models.SET_NULL,
                                            related_name="+")
    footer_quality_heading = models.CharField(max_length=100, blank=True)
    footer_quality_subheading = models.CharField(max_length=100, blank=True)
    footer_quality_text = models.TextField(blank=True, help_text="HTML")
    footer_chart_page = models.ForeignKey("wagtailcore.Page", null=True, blank=True, on_delete=models.SET_NULL,
                                          related_name="+")
    footer_chart_heading = models.CharField(max_length=100, blank=True)
    footer_chart_subheading = models.CharField(max_length=100, blank=True)
    footer_chart_text = models.TextField(blank=True, help_text="HTML")
    footer_sectors_page = models.ForeignKey("wagtailcore.Page", null=True, blank=True, on_delete=models.SET_NULL,
                                            related_name="+")
    footer_sectors_heading = models.CharField(max_length=100, blank=True)
    footer_sectors_subheading = models.CharField(max_length=100, blank=True)
    footer_sectors_text = models.TextField(blank=True, help_text="HTML")
    footer_contact_heading = models.CharField(max_length=100, blank=True)
    footer_contact_subheading = models.CharField(max_length=100, blank=True)
    footer_address = models.TextField(blank=True, help_text="HTML")
    footer_telephone_label = models.CharField(max_length=100, default="Tel:")
    footer_email_label = models.CharField(max_length=100, blank=True)

    analytics_id = models.CharField("Google Analytics ID", max_length=50, blank=True)
    google_site_verification = models.CharField(max_length=100, blank=True)
    bing_site_verification = models.CharField(max_length=100, blank=True)

    panels = [
        MultiFieldPanel([FieldPanel("telephone"), FieldPanel("email")], "Contact details"),
        MultiFieldPanel(
            [FieldPanel("tagline"), FieldPanel("tagline_highlight"), FieldPanel("telephone_label"),
             FieldPanel("contact_page"), FieldPanel("emergency_page")],
            "Header",
        ),
        MultiFieldPanel(
            [FieldPanel("products_heading"), FieldPanel("stock_box_page"), FieldPanel("stock_box_heading"),
             FieldPanel("stock_box_text"), FieldPanel("certificate_link"), FieldPanel("sidebar_text")],
            "Left column",
        ),
        MultiFieldPanel(
            [FieldPanel("read_more"),
             FieldPanel("footer_quality_page"), FieldPanel("footer_quality_heading"),
             FieldPanel("footer_quality_subheading"), FieldPanel("footer_quality_text"),
             FieldPanel("footer_chart_page"), FieldPanel("footer_chart_heading"),
             FieldPanel("footer_chart_subheading"), FieldPanel("footer_chart_text"),
             FieldPanel("footer_sectors_page"), FieldPanel("footer_sectors_heading"),
             FieldPanel("footer_sectors_subheading"), FieldPanel("footer_sectors_text"),
             FieldPanel("footer_contact_heading"), FieldPanel("footer_contact_subheading"),
             FieldPanel("footer_address"), FieldPanel("footer_telephone_label"), FieldPanel("footer_email_label")],
            "Footer",
        ),
        MultiFieldPanel(
            [FieldPanel("analytics_id"), FieldPanel("google_site_verification"), FieldPanel("bing_site_verification")],
            "Search engines and statistics",
        ),
    ]

    class Meta:
        verbose_name = _("site details")
