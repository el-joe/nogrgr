// Sign in the QA shopper; reports where the store sends an unverified account.
export default async ({ page, pw, BASE, shot, log }) => {
    const email = process.env.QA_EMAIL || 'qa-themes-shopper@example.test';
    await page.goto(BASE + '/account/login', { waitUntil: 'networkidle' });
    await page.fill('form:has(input[type=password]) input[name=email]', email);
    await page.fill('form:has(input[type=password]) input[name=password]', pw);
    await page.locator('form:has(input[type=password]) button[type=submit]').click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(800);
    log('after login:', page.url());
    log((await page.locator('main').innerText()).slice(0, 400).replace(/\s+/g, ' '));
    await shot('login-after');
};
