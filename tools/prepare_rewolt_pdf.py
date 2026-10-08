"""Prepare the Rewolt table layout for reviewed OpenCart CSV import, never update a store."""
import argparse
import csv
import json
import re
from decimal import Decimal, InvalidOperation
from pathlib import Path
import pdfplumber


def money(value):
    cleaned = re.sub(r'\s+', '', value or '').replace(',', '.')
    try:
        number = Decimal(cleaned)
    except InvalidOperation as error:
        raise ValueError(f'Invalid price: {value!r}') from error
    if not number.is_finite() or number < 0:
        raise ValueError(f'Invalid price: {value!r}')
    return str(number)


def quantity(value, approximate, assumed):
    value = re.sub(r'\s+', ' ', value or '').strip()
    match = re.fullmatch(r'(>?)[ ]*(\d+)', value)
    if not match:
        raise ValueError(f'Unknown quantity: {value!r}')
    if match[1] and approximate == 'keep':
        return ''
    if match[1] and approximate == 'fixed':
        return str(assumed)
    return match[2]


def prepare(source, output, price='retail', approximate='lower_bound', assumed=100, photos=True):
    if output.exists():
        raise ValueError('Output directory already exists; choose a new directory to preserve prior results.')
    output.mkdir(parents=True)
    photo_dir = output/'upload/image/catalog/supplier_rewolt'
    rows, warnings, seen = [], [], set()
    with pdfplumber.open(source) as document:
        for page_index, page in enumerate(document.pages, 1):
            tables = page.find_tables()
            if not tables:
                raise ValueError(f'No recognized table on page {page_index}; manual review required.')
            for table in tables:
                extracted = table.extract()
                for row_index, cells in enumerate(extracted):
                    if not cells or len(cells) != 7:
                        raise ValueError(f'Unexpected table width on page {page_index}, row {row_index+1}')
                    cells = [re.sub(r'\s+', ' ', c or '').strip() for c in cells]
                    if cells[1] == 'Артикул':
                        continue
                    if not any(cells):
                        continue
                    _, sku, name, retail, wholesale, stock, category = cells
                    if not sku or not name or sku in seen:
                        raise ValueError(f'Missing or duplicate identifier on page {page_index}: {sku!r}')
                    seen.add(sku)
                    image = ''
                    photo_box = table.rows[row_index].cells[0]
                    if photos and photo_box and any(i['x0'] < photo_box[2] and i['x1'] > photo_box[0] and i['top'] < photo_box[3] and i['bottom'] > photo_box[1] for i in page.images):
                        filename = re.sub(r'[^a-zA-Z0-9_-]', '_', sku) + '.png'
                        photo_dir.mkdir(parents=True, exist_ok=True)
                        page.crop(photo_box).to_image(resolution=144).save(photo_dir/filename, format='PNG')
                        image = 'catalog/supplier_rewolt/'+filename
                    if stock.startswith('>'):
                        warnings.append({'page':page_index, 'sku':sku, 'stock_raw':stock, 'policy':approximate})
                    if photos and not image:
                        warnings.append({'page':page_index, 'sku':sku, 'warning':'No embedded photo found in this product row'})
                    rows.append({'sku':sku, 'name':name, 'price':money(retail if price == 'retail' else wholesale), 'purchase_price':money(wholesale), 'retail_price':money(retail), 'quantity':quantity(stock, approximate, assumed), 'stock_raw':stock, 'category':category, 'image':image, 'status':'0'})
    if not rows:
        raise ValueError('No products extracted')
    with (output/'products.csv').open('w', encoding='utf-8', newline='') as stream:
        writer = csv.DictWriter(stream, fieldnames=list(rows[0]), delimiter=';')
        writer.writeheader()
        writer.writerows(rows)
    (output/'review.json').write_text(json.dumps({'products':len(rows), 'photos':sum(bool(r['image']) for r in rows), 'selected_price':price, 'approximate_quantity_policy':approximate, 'warnings':warnings}, ensure_ascii=False, indent=2), encoding='utf-8')
    return len(rows)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('source', type=Path)
    parser.add_argument('output', type=Path)
    parser.add_argument('--price', choices=['retail','wholesale'], default='retail')
    parser.add_argument('--approximate', choices=['lower_bound','fixed','keep'], default='lower_bound')
    parser.add_argument('--assumed-quantity', type=int, default=100)
    parser.add_argument('--no-photos', action='store_true')
    args = parser.parse_args()
    if args.assumed_quantity < 0:
        parser.error('Assumed quantity must not be negative')
    print('Prepared products:',prepare(args.source, args.output, args.price, args.approximate, args.assumed_quantity, not args.no_photos))
