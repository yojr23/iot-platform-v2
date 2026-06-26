import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const frontRoot = path.resolve(__dirname, '..');
const repoRoot = path.resolve(frontRoot, '..');
const repoWideChecksAvailable =
  fs.existsSync(path.join(repoRoot, 'docker-compose.yml')) &&
  fs.existsSync(path.join(repoRoot, 'back', 'Dockerfile'));

const requiredFiles = [
  path.join(frontRoot, 'Dockerfile'),
  path.join(frontRoot, '.dockerignore')
];

if (repoWideChecksAvailable) {
  requiredFiles.push(
    path.join(repoRoot, 'back', 'Dockerfile'),
    path.join(repoRoot, 'docker-compose.yml')
  );
}

for (const file of requiredFiles) {
  if (!fs.existsSync(file)) {
    throw new Error(`Required Docker artifact is missing: ${path.relative(repoRoot, file)}`);
  }
}

const frontDockerfile = fs.readFileSync(path.join(frontRoot, 'Dockerfile'), 'utf8');
if (!frontDockerfile.includes('EXPOSE 5173')) {
  throw new Error('front/Dockerfile must expose port 5173.');
}

if (!frontDockerfile.includes('--host') || !frontDockerfile.includes('0.0.0.0')) {
  throw new Error('front/Dockerfile must run Vite with host 0.0.0.0.');
}

if (repoWideChecksAvailable) {
  const composeFile = fs.readFileSync(path.join(repoRoot, 'docker-compose.yml'), 'utf8');

  for (const serviceName of ['front:', 'back:', 'db:', 'redis:']) {
    if (!composeFile.includes(serviceName)) {
      throw new Error(`docker-compose.yml must include service ${serviceName.replace(':', '')}.`);
    }
  }
}

const envExample = fs.readFileSync(path.join(frontRoot, '.env.example'), 'utf8');
const forbiddenKeys = ['PUSHER_APP_SECRET', 'APP_KEY', 'DB_PASSWORD', 'MAIL_PASSWORD', 'AWS_SECRET_ACCESS_KEY'];
for (const line of envExample.split('\n')) {
  const trimmed = line.trim();

  if (!trimmed || trimmed.startsWith('#') || !trimmed.includes('=')) {
    continue;
  }

  const [key] = trimmed.split('=', 1);

  if (forbiddenKeys.includes(key)) {
    throw new Error(`front/.env.example must not expose secret-like key ${key}.`);
  }
}

if (!repoWideChecksAvailable) {
  console.log('Phase 7 frontend-local Docker structure looks complete. Repo-wide checks were skipped because the container does not mount the repository root.');
} else {
  console.log('Phase 7 Docker frontend structure looks complete.');
}
