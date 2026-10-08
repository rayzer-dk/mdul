"""Validate source and build reviewable OCMOD archives. Does not deploy or publish."""
import argparse
import hashlib
import json
import os
import re
import subprocess
import zipfile
from pathlib import Path
from xml.etree import ElementTree

ROOT = Path(__file__).resolve().parents[1]
RELEASES = {
    'import_export_pro': ('Import_Export_Pro', '3.7.0'),
    'supplier_sync_parser_pro': ('Supplier_Sync_Parser_Pro', '1.6.4'),
    'importxml_clean_pro': ('ImportXML_Clean_Pro', '1.4.3')
}


def run(command, env=None):
    result = subprocess.run(command, cwd=ROOT, env=env, capture_output=True)
    output = (result.stdout + result.stderr).decode('utf-8', errors='replace')
    if result.returncode or re.search(r'(?:Warning:|Fatal error:|Parse error:|Deprecated:)', output):
        raise RuntimeError(output)
    return output.strip()


def main(php_root, output, versions):
    report = {'php':{}, 'xml':[], 'archives':[], 'skipped':[]}
    paths = [p for p in (ROOT/'modules').rglob('*') if p.is_file()]
    for path in paths:
        if path.suffix.lower() in ['.php','.twig','.xml','.js','.css','.md','.txt','.json']:
            binary = path.read_bytes()
            if binary.startswith(b'\xef\xbb\xbf'):
                raise ValueError(f'UTF-8 BOM: {path}')
            binary.decode('utf-8')
        if path.suffix == '.xml':
            ElementTree.parse(path)
            report['xml'].append(path.relative_to(ROOT).as_posix())
    for version in versions:
        php = php_root/version/'php.exe'
        args = [str(php), '-n', '-d', 'extension_dir='+str(php.parent/'ext'), '-d', 'extension=mbstring']
        if (php.parent/'ext/php_zip.dll').is_file():
            args += ['-d','extension=zip']
        php_files = [p for p in paths if p.suffix == '.php']
        for path in php_files:
            run(args + ['-l', str(path)])
        tests = {}
        for test in ['regression.php','catalog_regression.php','matching.php','catalog_matching.php','supplier_matching.php','supplier_search.php','cron_policy.php','file_cron.php','cron_endpoints.php','update_stock_policy.php','off_guard.php','xml_image_safety.php']:
            tests[test] = run(args + ['tests/'+test])
        if (ROOT/'.local/spilna_product.html').exists() and (ROOT/'.local/spilna_product_ru.html').exists():
            tests['spilna_fixture.php'] = run(args + ['tests/spilna_fixture.php'])
        else:
            report['skipped'].append('Spilna HTML fixture test on PHP '+version+' (private local fixtures absent)')
        if all((ROOT/'.local'/name).exists() for name in ['sazagro_product.html','rewolt_product.html']):
            tests['supplier_sites_fixture.php'] = run(args + ['tests/supplier_sites_fixture.php'])
        else:
            report['skipped'].append('Sazagro/Rewolt HTML fixture tests on PHP '+version+' (private local fixtures absent)')
        test_env = os.environ.copy()
        if version in ['7.4','8.0'] and test_env.get('CCP_TEST_TWIG_AUTOLOAD_LEGACY'):
            test_env['CCP_TEST_TWIG_AUTOLOAD'] = test_env['CCP_TEST_TWIG_AUTOLOAD_LEGACY']
        if test_env.get('CCP_TEST_TWIG_AUTOLOAD'):
            tests['compile_twig.php'] = run(args + ['tests/compile_twig.php'], env=test_env)
        else:
            report['skipped'].append('Twig compilation on PHP '+version+' (provide compatible autoload path)')
        report['php'][version] = {'syntax_files':len(php_files),'tests':tests}
        print('PHP',version,':',len(php_files),'files;',len(tests),'test groups passed')
    output.mkdir(parents=True, exist_ok=True)
    for module, (archive_name, version) in RELEASES.items():
        source = ROOT/'modules'/module
        if not (source/'install.xml').is_file() or not (source/'upload').is_dir():
            raise ValueError('Missing OCMOD root: '+module)
        xml_version = ElementTree.parse(source/'install.xml').getroot().findtext('version')
        if xml_version != version:
            raise ValueError(f'Archive version mismatch: {module}: {xml_version}')
        archive = output/f'{archive_name}_v{version}.ocmod.zip'
        temp = archive.with_suffix('.zip.tmp')
        with zipfile.ZipFile(temp, 'w', zipfile.ZIP_DEFLATED) as z:
            z.writestr('upload/', b'')
            for path in sorted(source.rglob('*')):
                if path.is_file():
                    z.write(path, path.relative_to(source).as_posix())
        with zipfile.ZipFile(temp) as z:
            if z.testzip():
                raise ValueError('ZIP CRC failed')
            entries = z.namelist()
            if 'install.xml' not in entries or not any(e.startswith('upload/admin/') for e in entries):
                raise ValueError('Invalid ZIP contract')
        temp.replace(archive)
        report['archives'].append({'name':archive.name,'files':len(entries),'sha256':hashlib.sha256(archive.read_bytes()).hexdigest(),'bytes':archive.stat().st_size})
        print('Built',archive.name)
    (output/'verification.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8')


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--php-root',type=Path,required=True)
    parser.add_argument('--output',type=Path,default=ROOT/'dist')
    parser.add_argument('--php-versions', nargs='+', default=['7.4','8.0','8.1','8.2','8.3','8.4','8.5'])
    options = parser.parse_args()
    main(options.php_root.resolve(),options.output.resolve(),options.php_versions)
