from pathlib import Path
import shutil,sys
root=Path(__file__).resolve().parent.parent
if '--confirm' not in sys.argv: raise SystemExit('Stop the server, then pass --confirm to overwrite synthetic demo databases.')
for source in (root/'fixtures/data').rglob('*.sqlite'):
    target=root/'data'/source.relative_to(root/'fixtures/data')
    target.parent.mkdir(parents=True,exist_ok=True)
    shutil.copy2(source,target)
print('Synthetic demo databases restored.')
