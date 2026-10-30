"""Make the Arabic versions of the pictures with words on them (the
"Contact us now" and "Emergency delivery" buttons) and the sidebar arrows
pointing the other way, for the right-to-left Arabic site.

    python tools/make_arabic_images.py

Writes into website/static/frontend/i18n/ar/images/. Needs Pillow with
libraqm (for joining Arabic letters) and a font with Arabic letters.
"""
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

STATIC = Path(__file__).resolve().parent.parent / "website" / "static" / "frontend"
OUT = STATIC / "i18n" / "ar" / "images"
FONT = "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"


def font(size):
    return ImageFont.truetype(FONT, size, layout_engine=ImageFont.Layout.RAQM)


def blank_out(image, box, is_background):
    """Paint over the (light) words in box with the button's own colour,
    row by row, so the gradient and the picture beside them stay."""
    x0, y0, x1, y1 = box
    for y in range(y0, y1):
        row = [image.getpixel((x, y)) for x in range(x0, x1)]
        background = sorted((p for p in row if is_background(p)), key=sum)
        if not background:
            continue
        colour = background[len(background) // 2]
        for x, p in zip(range(x0, x1), row):
            if p[3] > 0 and abs(sum(p[:3]) - sum(colour[:3])) > 30:
                image.putpixel((x, y), colour)


def write(image, box, lines, size):
    draw = ImageDraw.Draw(image)
    x0, y0, x1, y1 = box
    f = font(size)
    heights = [draw.textbbox((0, 0), line, font=f, direction="rtl", language="ar") for line in lines]
    total = sum(b[3] - b[1] for b in heights) + 2 * (len(lines) - 1)
    y = y0 + (y1 - y0 - total) / 2
    for line, bbox in zip(lines, heights):
        width = bbox[2] - bbox[0]
        draw.text(((x0 + x1 - width) / 2 - bbox[0], y - bbox[1]), line, font=f, fill="white",
                  direction="rtl", language="ar")
        y += bbox[3] - bbox[1] + 2


def main():
    OUT.joinpath("header").mkdir(parents=True, exist_ok=True)
    OUT.joinpath("sidebar").mkdir(parents=True, exist_ok=True)

    contact = Image.open(STATIC / "images/header/contact-button.png").convert("RGBA")
    blank_out(contact, (8, 10, 111, 21), lambda p: p[2] > p[0] + 60 and p[3] > 200)
    write(contact, (12, 4, 106, 28), ["اتصل بنا الآن"], 13)
    contact.save(OUT / "header/contact-button.png")

    emergency = Image.open(STATIC / "images/header/emergency.png").convert("RGBA")
    blank_out(emergency, (40, 4, 115, 39), lambda p: p[0] > 180 and p[1] < 90 and p[3] > 200)
    write(emergency, (44, 5, 113, 36), ["التسليم", "العاجل <"], 10)
    emergency.save(OUT / "header/emergency.png")

    for name in ("menu-arrow.png", "menu-arrow-hover.png"):
        Image.open(STATIC / "images/sidebar" / name).transpose(Image.Transpose.FLIP_LEFT_RIGHT).save(OUT / "sidebar" / name)


if __name__ == "__main__":
    main()
