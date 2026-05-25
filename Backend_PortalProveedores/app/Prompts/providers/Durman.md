### DURMAN SPECIFIC RULES
- **Identification**: Durman invoices typically say "Durman by aliaxis" and "Corporacion de Inversiones Dureco S.A.".
- **Math & Taxes (montoImpuesto)**: You MUST extract `0.0` for `montoImpuesto` on every single item. DO NOT extract the actual tax amount for the items. This ensures the system does not add taxes to the row total.
- **Purchase Order (no_pedido)**: Look for **"Orden de compra:"** (e.g., "VOL-211507") at the bottom of the invoice.
