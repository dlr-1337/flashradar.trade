import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const targets = [
  path.join(root, 'price_data', 'index.php'),
  path.join(root, 'price_data', 'login.php'),
  path.join(root, 'price_data', 'admin.php'),
  path.join(root, 'price_data', 'change-password.php'),
].filter((file) => fs.existsSync(file));

if (targets.length === 0) {
  console.error('Missing frontend PHP entrypoints.');
  process.exit(1);
}

const disallowed = [
  'https://flashradar.trade/favicon.ico',
];

for (const target of targets) {
  const html = fs.readFileSync(target, 'utf8');
  const scripts = [...html.matchAll(/<script(?![^>]*\bsrc=)[^>]*>([\s\S]*?)<\/script>/gi)];

  for (const fragment of disallowed) {
    if (html.includes(fragment)) {
      console.error(`Frontend entry still depends on remote asset: ${fragment} in ${path.basename(target)}`);
      process.exit(1);
    }
  }

  for (const match of scripts) {
    try {
      const source = match[1].replace(/<\?=([\s\S]*?)\?>/g, 'null');
      new Function(source);
    } catch (error) {
      console.error(`Inline script parse failed in ${path.basename(target)}.`);
      console.error(error instanceof Error ? error.message : String(error));
      process.exit(1);
    }
  }
}

console.log(`Frontend scripts parsed successfully: ${targets.map((file) => path.basename(file)).join(', ')}`);