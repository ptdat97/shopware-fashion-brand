import type { APIRequestContext } from "@playwright/test";

/**
 * Hộp thư Mailpit local (backend/.env.local: MAILER_DSN=smtp://127.0.0.1:1025) — đọc email
 * Shopware vừa gửi. Không chạy Mailpit thì `isAvailable` trả false và test tự bỏ qua phần email.
 */
const base = () => process.env.E2E_MAILPIT_URL ?? "http://127.0.0.1:8025";

export async function isMailpitAvailable(
  request: APIRequestContext,
): Promise<boolean> {
  try {
    return (
      await request.get(`${base()}/api/v1/info`, { timeout: 2_000 })
    ).ok();
  } catch {
    return false;
  }
}

export type Mail = {
  subject: string;
  html: string;
  text: string;
  from: string;
};

/** Email mới nhất gửi tới `to` — chờ tối đa ~10 giây (email gửi sau khi đặt đơn) */
export async function waitForMail(
  request: APIRequestContext,
  to: string,
): Promise<Mail> {
  for (let attempt = 0; attempt < 20; attempt++) {
    const search = await request.get(`${base()}/api/v1/search`, {
      params: { query: `to:"${to}"` },
    });
    const { messages } = (await search.json()) as {
      messages: Array<{ ID: string }>;
    };
    if (messages.length > 0) {
      const message = await (
        await request.get(`${base()}/api/v1/message/${messages[0]!.ID}`)
      ).json();
      return {
        subject: message.Subject,
        html: message.HTML,
        text: message.Text,
        from: message.From?.Name ?? "",
      };
    }
    await new Promise((resolve) => setTimeout(resolve, 500));
  }
  throw new Error(`Mailpit: không có email nào gửi tới ${to}`);
}
