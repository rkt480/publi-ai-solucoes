const { chromium } = require("/Users/raphael/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright");
const path = require("path");

(async () => {
  const browser = await chromium.launch({
    headless: true,
    executablePath: "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome",
  });
  const page = await browser.newPage({ viewport: { width: 1080, height: 1350 }, deviceScaleFactor: 1 });
  for (const slide of ["01", "02", "03", "04", "05"]) {
    const filePath = path.resolve(__dirname, `slide-${slide}.html`);
    await page.goto(`file://${filePath}`, { waitUntil: "networkidle" });
    await page.screenshot({
      path: path.resolve(__dirname, `slide-${slide}.png`),
      clip: { x: 0, y: 0, width: 1080, height: 1350 },
    });
  }
  await browser.close();
})();
