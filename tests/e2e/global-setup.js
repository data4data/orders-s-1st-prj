import { spawn } from 'node:child_process';

/**
 * Starts a Messenger worker for the browser tests: payment webhooks and emails are processed
 * asynchronously (decision #23). Returning a function makes Playwright stop it afterwards.
 */
export default async function globalSetup() {
    const worker = spawn('php', ['bin/console', 'messenger:consume', 'async', '--time-limit=1200', '--quiet'], { stdio: 'ignore' });
    return () => worker.kill();
}
