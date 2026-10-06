from pathlib import Path
import subprocess,os
root=Path(__file__).resolve().parents[1]
for file in root.rglob('*.php'):
 subprocess.run([os.environ.get('PHP_BINARY','php'),'-l',str(file)],check=True,stdout=subprocess.DEVNULL)
print('PASS: all PHP source files parse.')
