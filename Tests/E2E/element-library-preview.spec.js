import { expect, test } from "@playwright/test";

test("element library preview page resolves its Fluid template", async ({ request }) => {
  const response = await request.get("/?type=1777200001");
  const body = await response.text();

  expect(response.status()).toBeLessThan(400);
  expect(body).toContain('<main class="desiderio-element-preview">');
  expect(body).not.toContain("InvalidTemplateResourceException");
});
