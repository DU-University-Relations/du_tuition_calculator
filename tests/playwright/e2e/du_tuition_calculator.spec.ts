import { test, expect } from '@du_pw/test';
import { getRole, logInViaForm, logOutViaUi } from "@du_pw/support/users";

test.describe('@tuition_calculator - Tuition Calculator E2E Tests', () => {

  const CALC_URL = '/admission-aid/tuition-calculator';

  test('loads the DU Tuition Calculator page', async ({ page }) => {
    // Navigate to the calculator page
    await page.goto(CALC_URL);

    // Verify successfully reached the page
    await expect(page).toHaveURL(new RegExp(`${CALC_URL}.*`));

    // Verify the page rendered 
    await expect(page.locator('body')).toBeVisible();
  });
});