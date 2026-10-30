from wagtail import blocks
from wagtail.images.blocks import ImageBlock

# What the editor offers in text areas: what the old sites' content uses.
RICH_TEXT_FEATURES = [
    "h1", "h2", "h3", "h4", "h5", "bold", "italic", "ol", "ul", "hr",
    "link", "document-link", "image",
]


class ContentBlock(blocks.StreamBlock):
    """An editable area of a page.

    Mostly "text" (the normal editor). Content brought over from the old
    sites that the editor cannot represent exactly - tables, coloured or
    underlined text, images from other websites - is kept as an "HTML" block
    so that it looks exactly as it did.
    """

    text = blocks.RichTextBlock(features=RICH_TEXT_FEATURES, icon="pilcrow")
    html = blocks.RawHTMLBlock(label="HTML", icon="code")
    image = ImageBlock(icon="image")

    class Meta:
        template = "website/blocks/content.html"


class ImageRowBlock(blocks.StructBlock):
    """Text on the left, picture on the right (product pages with a picture per paragraph)."""

    text = ContentBlock(required=False)
    image = ImageBlock(required=False)

    class Meta:
        icon = "doc-full"
        label = "Text and picture"
