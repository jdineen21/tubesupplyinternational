"""Writes tests/seed.sql: test content for the Atlas CMS.

The page tree mirrors the live tubesupplyinternational.co.uk site (same
branch ids, URLs and menus) and every template, region type and editor
gets at least one row, so the crawl in run_tests.py exercises all of it.
Text deliberately includes accents, Cyrillic and HTML.
"""
import hashlib
import json
import os

HERE = os.path.dirname(os.path.abspath(__file__))


def encrypt(s):
    # Same as encrypt() in admin/inc/functions.php
    salt = hashlib.sha1(hashlib.md5(s.encode()).hexdigest().encode()).hexdigest()
    return hashlib.md5((s + salt).encode()).hexdigest()


def q(v):
    if v is None:
        return 'NULL'
    if isinstance(v, int):
        return str(v)
    return "'" + str(v).replace('\\', '\\\\').replace("'", "\\'") + "'"


out = []


def insert(table, **cols):
    out.append('INSERT INTO %s (%s) VALUES (%s);' % (
        table, ', '.join(cols), ', '.join(q(v) for v in cols.values())))


TEMPLATES = ['404', 'contact', 'home', 'offline', 'page', 'page-image-column',
             'page-slider', 'page-slider-body-image', 'page-two-column',
             'product', 'size-conversion-chart', 'two-column-lower-image']
T = {}
for i, name in enumerate(TEMPLATES, start=1):
    T[name] = i
    insert('a_templates', templateId=i, templateName=name,
           type='product' if name == 'product' else 'page',
           allowNew='N' if name in ('404', 'offline') else 'Y')

insert('a_menus', menuId=1, menuName='Main Navigation', maxDepth=2)
insert('a_menus', menuId=2, menuName='Product Menu', maxDepth=2)

insert('a_branchtypes', typeId=1, typeName='page', singularName='page', pluralName='pages',
       orderBy='sortOrder', orderByDirection='ASC', sortable='N', inStructure='Y',
       visibleInCMS='Y', allowNew='Y', allowMultiUpload='Y', cmsOrder=1)
insert('a_branchtypes', typeId=2, typeName='product', singularName='product', pluralName='products',
       orderBy='title', orderByDirection='ASC', sortable='Y', inStructure='Y',
       visibleInCMS='Y', allowNew='Y', allowMultiUpload='Y', defaultParentId=6,
       defaultMenuId=2, cmsOrder=2)
insert('a_branchtypes', typeId=3, typeName='news', singularName='news item', pluralName='news',
       orderBy='dateAdded', orderByDirection='DESC', sortable='N', inStructure='N',
       visibleInCMS='Y', allowNew='Y', cmsOrder=3)
insert('a_branchtypes', typeId=4, typeName='link', singularName='link', pluralName='links',
       sortable='N', inStructure='Y', visibleInCMS='N', allowNew='N', cmsOrder=4)

# (branchId, parentId, type, template, title, urlSegment, menuId, sortOrder)
TREE = [
    (1, 0, 'page', 'home', 'Heat Exchanger Tubes & Pipe Suppliers', '', 1, 1),
    (20, 0, 'page', 'page-slider', 'Industrial Sectors', 'sectors', 1, 2),
    (3, 0, 'page', 'page-two-column', 'Quality Systems', 'quality-systems', 1, 3),
    (4, 0, 'page', 'size-conversion-chart', 'Size Conversion Chart', 'size-conversion-chart', 1, 4),
    (5, 0, 'page', 'contact', 'Contact Information', 'contact-us', 0, 0),
    (2, 0, 'page', 'page-slider-body-image', 'Emergency Delivery', 'emergency-delivery', 0, 0),
    (6, 0, 'page', 'page', 'Products', 'products', 0, 0),
    (7, 6, 'product', 'product', 'Seamless Cold Drawn Tubes', 'seamless-cold-drawn-tubes', 2, 1),
    (8, 6, 'product', 'product', 'Welded Tubes', 'welded-tubes', 2, 2),
    (9, 6, 'product', 'page-slider', 'U Tubes', 'u-tubes', 2, 3),
    (10, 6, 'product', 'product', 'Alloy Steel Furnace Tubes', 'alloy-steel-furnace-tubes', 2, 4),
    (11, 6, 'product', 'page-image-column', 'Helical High Finned Tubes', 'helical-high-finned-tubes', 2, 5),
    (12, 6, 'product', 'product', 'HF Welded Helical Finned Tubes', 'high-frequency-helical-finned-tubes', 2, 6),
    (13, 6, 'product', 'product', 'Integrally Finned Tubes', 'integrally-finned-tubes', 2, 7),
    (14, 6, 'product', 'product', 'Studded Tube and Pipe', 'studded-tube-and-pipe', 2, 8),
    (15, 6, 'product', 'product', 'Longitudinal Finned Tubes', 'longitudinal-finned-tubes', 2, 9),
    (16, 6, 'product', 'page', 'Corrosion Protection E-Fin', 'corrosion-protection-e-fin', 0, 0),
    (17, 6, 'product', 'page-slider', 'Associated Products', 'associated-products', 2, 10),
    (18, 6, 'product', 'two-column-lower-image', 'Plain Stock Tubes', 'plain-stock-tubes', 0, 0),
    (19, 6, 'product', 'page-two-column', 'Stock Heat Exchanger Tubes', 'stock-heat-exchanger-tubes', 2, 11),
    # nested page in the main menu, so submenus render
    (21, 3, 'page', 'page', 'Certificates – ISO 9001', 'certificates', 1, 1),
    (22, 0, 'page', 'offline', 'Site Offline', 'offline', 0, 0),
    (23, 0, 'link', 'page', 'Parent Company', 'parent-company', 1, 5),
    (24, 0, 'news', 'page', 'Новости: new mill in Spain', 'news-new-mill', 0, 0),
    (25, 0, 'news', 'page', 'Second news item', 'news-second', 0, 0),
]
for branchId, parentId, typ, tpl, title, seg, menuId, sortOrder in TREE:
    insert('a_tree', branchId=branchId, parentId=parentId, type=typ, templateId=T[tpl],
           title=title, navTitle=title.split(' – ')[0], subTitle='Sub ' + seg if menuId == 1 else '',
           urlSegment=seg,
           description='http://www.example.com/' if typ == 'link' else 'Description of %s – tubos, трубы' % title,
           keywords='tubes, %s' % seg, menuId=menuId, sortOrder=sortOrder,
           allowDelete='N' if branchId in (1, 22) else 'Y',
           dateAdded='2013-07-%02d 09:30:00' % (branchId % 28 + 1), editedBy='Stephen')

insert('a_settings', homeBranchId=1, offlineBranchId=22, offlineMode='N', analyticsUA='UA-123456-1')

region_id = [0]
REGIONS = {}  # (templateName, regionName) -> regionId


def region(tpl, name, rtype='html', glob='N', repeat=0, order=0, params=None, desc=''):
    region_id[0] += 1
    rid = region_id[0]
    insert('a_regions', regionId=rid, templateId=T[tpl] if tpl else 0, regionName=name,
           regionType=rtype, **{'global': glob}, repeatRegionId=repeat, cmsOrder=order,
           description=desc, parameters=json.dumps(params) if params else '')
    REGIONS[(tpl, name)] = rid
    return rid


# global regions (edited under "Edit Global Content")
region(None, 'Telephone Number', 'text', glob='Y', order=1)
region(None, 'Email Address', 'text', glob='Y', order=2)
region(None, 'Emergency Number', 'text', glob='Y', order=3)

TEMPLATE_REGIONS = {
    'home': [('Page Header Text', 'html'), ('Home Page Content', 'html')],
    'contact': [('Page Header Text', 'html'), ('Left Column Text', 'html'), ('Right Column Text', 'html')],
    'page': [('Page Header Text', 'html'), ('Page Image', 'image'), ('Page Content', 'html')],
    'product': [('Page Image', 'image'), ('Page Content', 'html')],
    'page-slider': [('Page Header Text', 'html'), ('Page Content', 'html')],
    'page-slider-body-image': [('Page Header Text', 'html'), ('Page Content', 'html')],
    'page-two-column': [('Page Content Left', 'html'), ('Page Content Right', 'html')],
    'two-column-lower-image': [('Page Image', 'image'), ('Page Content', 'html'), ('Intro Text', 'text'),
                               ('Left Column Text', 'html'), ('Right Column Text', 'html')],
    'page-image-column': [('Page Image', 'image'), ('Page Intro', 'html'), ('Full Width Page Text', 'html')]
    + [('Product Text %d' % i, 'html') for i in range(1, 6)]
    + [('Product Image %d' % i, 'image') for i in range(1, 6)],
}
IMAGE_PARAMS = {'crop': True, 'cropWidth': 300, 'cropHeight': 200, 'quality': 85}
for tpl, regions in TEMPLATE_REGIONS.items():
    for order, (name, rtype) in enumerate(regions, start=1):
        params = None
        if rtype == 'image':
            params = IMAGE_PARAMS if name == 'Page Image' else {'maxWidth': 400, 'maxHeight': 300}
        region(tpl, name, rtype, order=order, params=params)

# One region of every remaining editor type on the plain 'page' template,
# so content-edit.php includes each editor.
EXTRA = [
    ('Plain Text', 'text', {'maxChars': 200}),
    ('Text With HTML', 'text', {'allowHTML': True}),
    ('Related Page', 'link', None),
    ('Background Colour', 'colour', {'defaultColour': '#336699'}),
    ('Event Date', 'datetime', {'mode': 'datetime'}),
    ('Price', 'number', {'mode': 'currency'}),
    ('Pick A News Item', 'dropdown', {'typeName': 'news'}),
    ('Featured Child', 'featured', None),
    ('Show Banner', 'boolean', {'default': True, 'trueName': 'Yes', 'falseName': 'No'}),
    ('Category', 'category', {'typeName': 'page'}),
    ('Post Date', 'postdate', None),
    ('Thumb Image', 'image', {'customThumbWidth': 120, 'customThumbHeight': 90}),
]
for order, (name, rtype, params) in enumerate(EXTRA, start=10):
    region('page', name, rtype, order=order, params=params)

# repeat regions: slider rows on both slider templates
for rr_id, tpl in ((1, 'page-slider'), (2, 'page-slider-body-image')):
    insert('a_repeatregions', repeatRegionId=rr_id, templateId=T[tpl], regionName='Slider Images',
           description='Images for the slider')
    region(tpl, 'Slider Image', 'image', repeat=rr_id, order=1, params={'maxWidth': 700, 'maxHeight': 300})
    region(tpl, 'Caption', 'text', repeat=rr_id, order=2)

IMAGES = iter([11, 16, 20, 22, 24, 26, 28, 30, 32, 34, 38, 42, 45, 50, 56, 63, 65, 67, 70, 74,
               75, 84, 85, 86, 89, 91, 93, 94, 97, 98, 104, 105, 106])
content_id = [1000]


def content(branchId, tpl, name, value, status='published', rowId=0, params=''):
    rid = REGIONS[(tpl, name)]
    if value == 'IMAGE':
        cid = next(IMAGES)
        value = '/media/images/rendered/%d.jpg' % cid
        params = params or json.dumps({'alt': 'Tube photo %d' % cid})
    else:
        content_id[0] += 1
        cid = content_id[0]
    insert('a_content', contentId=cid, branchId=branchId, regionId=rid, repeatRegionRowId=rowId,
           content=value, parameters=params, status=status)


content(0, None, 'Telephone Number', '+44 (0)191 123 4567')
content(0, None, 'Email Address', 'sales@example.com')
content(0, None, 'Emergency Number', '+44 (0)7700 900123')

row_id = [0]
by_id = {b[0]: b for b in TREE}
for branchId, _, _, tpl, title, _, _, _ in TREE:
    for name, rtype in TEMPLATE_REGIONS.get(tpl, []):
        if rtype == 'image':
            content(branchId, tpl, name, 'IMAGE')
        elif rtype == 'text':
            content(branchId, tpl, name, 'Intro for %s – açúcar, crème brûlée, Привет' % title)
        else:
            content(branchId, tpl, name,
                    '<h1>%s</h1><p>Text for <strong>%s</strong> – ñ é ü ç ß € “quotes” Трубы.</p>'
                    '<p><a href="/contact-us" target="_blank">Contact us</a></p>' % (title, name))
    if tpl in ('page-slider', 'page-slider-body-image'):
        rr = 1 if tpl == 'page-slider' else 2
        for n in range(1, 4):
            row_id[0] += 1
            insert('a_repeatregionsrows', rowId=row_id[0], repeatRegionId=rr, templateId=T[tpl],
                   branchId=branchId, sortOrder=n, displayName='Slide %d' % n)
            content(branchId, tpl, 'Slider Image', 'IMAGE', rowId=row_id[0])
            content(branchId, tpl, 'Caption', 'Slide %d caption – ü' % n, rowId=row_id[0])

# a draft that differs from the published text (admin preview shows the draft)
content(7, 'product', 'Page Content', '<p>DRAFT version of seamless tubes – not live yet</p>', status='draft')

# values for the extra editors on the Certificates page (branch 21)
for name, value in [('Plain Text', 'Plain text value'), ('Text With HTML', '<em>emphasis</em>'),
                    ('Related Page', '7'), ('Background Colour', '#ff0000'),
                    ('Event Date', '2013-07-11 09:39'), ('Price', '12.50'),
                    ('Pick A News Item', '24'), ('Show Banner', 'true')]:
    content(21, 'page', name, value)

insert('a_downloads', fileId=1, title='TSI Brochure', description='Company brochure', fileName='TSI_Brochure.pdf',
       branchId=7, fileType='pdf', sortOrder=1)
insert('a_downloads', fileId=2, title='Certificate', description='ISO certificate', fileName='TSI_Certificate.pdf',
       branchId=7, fileType='pdf', sortOrder=2)

insert('a_users', userId=1, username='Admin', email='admin@example.com', password=encrypt('secret1'),
       loginType='admin', lastLogin='2013-07-11 09:39:00')
insert('a_users', userId=2, username='Developer', email='dev@example.com', password=encrypt('devpass'),
       loginType='developer', lastLogin='2013-07-10 10:00:00')
insert('a_users', userId=3, username='Viewer', email='viewer@example.com', password=encrypt('viewpass'),
       loginType='viewer', lastLogin=None)
insert('a_users', userId=4, username='Climb', email='mailus@climbcreative.co.uk', password=encrypt('x'),
       loginType='developer', lastLogin=None)

insert('dev_tasks', taskId=1, task='Check the sitemap', complete='N', sortOrder=1)
insert('dev_tasks', taskId=2, task='Upgrade PHP', complete='Y', sortOrder=2)

with open(os.path.join(HERE, 'seed.sql'), 'w', encoding='utf-8') as f:
    f.write('-- Generated by make_seed.py; do not edit by hand.\nSET NAMES utf8mb4;\n')
    f.write('\n'.join(out) + '\n')
print('wrote %d rows' % len(out))
