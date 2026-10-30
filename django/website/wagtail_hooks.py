from wagtail import hooks
from wagtail.admin.rich_text.converters.html_to_contentstate import InlineStyleElementHandler
from wagtail.admin.rich_text.editors.draftail import features as draftail_features


@hooks.register("register_rich_text_features", order=100)
def bold_and_italic_as_strong_and_em(features):
    """Save bold and italic as <strong> and <em> (Wagtail's default is <b>
    and <i>): the sites' stylesheet styles strong and em."""
    for name, style, tag, icon in [("bold", "BOLD", "strong", "bold"), ("italic", "ITALIC", "em", "italic")]:
        features.register_editor_plugin(
            "draftail", name,
            draftail_features.InlineStyleFeature({"type": style, "icon": icon, "description": name.title()}),
        )
        features.register_converter_rule("contentstate", name, {
            "from_database_format": {
                "b" if name == "bold" else "i": InlineStyleElementHandler(style),
                tag: InlineStyleElementHandler(style),
            },
            "to_database_format": {"style_map": {style: tag}},
        })
