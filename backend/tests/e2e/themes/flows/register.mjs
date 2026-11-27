// Register a shopper (intl phone), then show the e-mail verification page.
export default async ({ page, pw, BASE, shot, log }) => {
    const email = process.env.QA_EMAIL || 'qa-themes-shopper@example.test';
    await page.goto(BASE + '/account/register', { waitUntil: 'networkidle' });
    const iti = await page.evaluate(() => document.querySelector('.iti__selected-country')?.getAttribute('title') || document.querySelector('.iti')?.innerText?.slice(0, 40));
    log('intl-tel-input selected country:', iti);
    await page.fill('#sf-name', 'QA Themes Shopper');
    await page.fill('#sf-email', email);
    await page.fill('#sf-phone', '551234567');
    await page.fill('#sf-password', pw);
    await page.fill('#sf-password-confirmation', pw);
    await shot('register-filled');
    await page.locator('form:has(#sf-email) button[type=submit]').click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(800);
    log('after register:', page.url());
    log((await page.locator('main').innerText()).slice(0, 500).replace(/\s+/g, ' '));
    await shot('register-after');
};
