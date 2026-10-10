import { defineConfig, devices } from "@playwright/test";
import { readFileSync } from "node:fs";

const baseURL = process.env.PLAYWRIGHT_BASE_URL
  ?? "https://webconsulting-typo3-lab.ddev.site";
const httpCredentials = process.env.PLAYWRIGHT_HTTP_CREDENTIALS_FILE
  ? JSON.parse(readFileSync(process.env.PLAYWRIGHT_HTTP_CREDENTIALS_FILE, 'utf8'))
  : undefined;

export default defineConfig({
  testDir: "./Tests/E2E",
  outputDir: "./var/playwright/results",
  reporter: [
    ["line"],
    ["html", { outputFolder: "./var/playwright/report", open: "never" }],
  ],
  fullyParallel: true,
  forbidOnly: Boolean(process.env.CI),
  retries: process.env.CI ? 1 : 0,
  timeout: 30_000,
  expect: { timeout: 5_000 },
  use: {
    baseURL,
    httpCredentials,
    ignoreHTTPSErrors: true,
    screenshot: "only-on-failure",
    trace: "retain-on-failure",
  },
  projects: [
    {
      name: "chromium-desktop",
      use: { ...devices["Desktop Chrome"] },
    },
    {
      name: "chromium-mobile",
      use: { ...devices["Pixel 7"] },
    },
  ],
});
