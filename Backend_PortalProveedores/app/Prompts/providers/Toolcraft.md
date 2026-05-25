### TOOLCRAFT SPECIFIC RULES
- **Identification**: Toolcraft invoices usually say "COMERCIAL DE HERRAMIENTAS DE GUATEMALA, S.A." and "TOOLCRAFT".
- **Discounts (montoDescuento)**: You MUST extract the value from the "Descuentos" column in the table and assign it to the `montoDescuento` field for each item. This is critical. If the column says "0.00", extract `0.0`. Do not ignore the discount column.
- **Purchase Order (no_pedido)**: Look for **"No. OC Cliente"** (e.g., "vol212035") in the header table.
- **Taxes (montoImpuesto)**: Extract the value from the "Impuesto IVA" column for each item.
