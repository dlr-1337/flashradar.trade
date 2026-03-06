import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const candidates = [
  path.join(root, 'price_data', 'index.php'),
  path.join(root, 'price_data', 'index.html'),
];

const target = candidates.find((file) => fs.existsSync(file));

if (!target) {
  console.error('Missing dashboard entrypoint.');
  process.exit(1);
}

const html = fs.readFileSync(target, 'utf8');
const match = html.match(/<script>([\s\S]*?)<\/script>\s*<\/body>/i);

if (!match) {
  console.error(`Missing inline dashboard script in ${path.basename(target)}.`);
  process.exit(1);
}

try {
  const source = match[1].replace(/<\?=([\s\S]*?)\?>/g, 'null');
  new Function(source);
} catch (error) {
  console.error(`Dashboard script parse failed in ${path.basename(target)}.`);
  console.error(error instanceof Error ? error.message : String(error));
  process.exit(1);
}

const disallowed = [
  'https://flashradar.trade/favicon.ico',
];

for (const fragment of disallowed) {
  if (html.includes(fragment)) {
    console.error(`Dashboard entry still depends on remote asset: ${fragment}`);
    process.exit(1);
  }
}

console.log(`Dashboard script parsed successfully: ${path.basename(target)}`);
