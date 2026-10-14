# pm_usdt — JoomShopping USDT (TRC20)

For JoomShopping 5.x / Joomla 4+.

1. Copy this folder to `components/com_jshopping/payments/pm_usdt`
2. Admin → JoomShopping → Options → Payment methods → New
3. Name: USDT (TRC20), Class: `pm_usdt`, publish it
4. Gateway URL and API key are pre-filled; adjust if needed
5. Set End/Pending/Failed transaction statuses in the plugin params

Requires PHP 7.4+, no composer, no shell access.