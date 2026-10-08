import importlib.util
import unittest
from pathlib import Path

spec = importlib.util.spec_from_file_location('rewolt', Path(__file__).resolve().parents[1]/'tools/prepare_rewolt_pdf.py')
rewolt = importlib.util.module_from_spec(spec)
spec.loader.exec_module(rewolt)


class RewoltNormalization(unittest.TestCase):
    def test_prices_with_spaces(self):
        self.assertEqual(rewolt.money('1\u00a0291,50'), '1291.50')

    def test_unknown_price_rejected(self):
        for value in ['договірна', 'NaN', '-1']:
            with self.assertRaises(ValueError):
                rewolt.money(value)

    def test_approximate_stock_policy(self):
        self.assertEqual(rewolt.quantity('> 10', 'lower_bound', 100), '10')
        self.assertEqual(rewolt.quantity('> 10', 'fixed', 100), '100')
        self.assertEqual(rewolt.quantity('> 10', 'keep', 100), '')
        self.assertEqual(rewolt.quantity('2', 'fixed', 100), '2')

    def test_unknown_quantity_requires_review(self):
        with self.assertRaises(ValueError):
            rewolt.quantity('уточніть', 'lower_bound', 100)


if __name__ == '__main__':
    unittest.main()
