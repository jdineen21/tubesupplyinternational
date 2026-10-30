from captcha.fields import CaptchaField, CaptchaTextInput
from django import forms
from django.utils.translation import gettext_lazy as _


class CaptchaInputs(CaptchaTextInput):
    """Just the captcha's inputs: the contact page shows the picture in a
    column of its own, from widget.image_url (set once this has rendered)."""

    template_name = "website/captcha_inputs.html"


class ContactForm(forms.Form):
    name = forms.CharField(max_length=255)
    contact = forms.CharField(max_length=255)
    email = forms.EmailField(max_length=255, required=False)
    job = forms.CharField(max_length=255, required=False)
    company = forms.CharField(max_length=255, required=False)
    country = forms.CharField(max_length=255, required=False)
    message = forms.CharField(widget=forms.Textarea, max_length=20000, required=False)
    captcha = CaptchaField(
        widget=CaptchaInputs(attrs={"id": "code", "class": "inputBox", "style": "width: 100px;", "autocomplete": "off"}),
        error_messages={"invalid": _("Code incorrect. Please enter the code again.")},
    )
