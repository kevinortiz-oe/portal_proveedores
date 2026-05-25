### FABRITUB SPECIFIC RULES
- **Item Code (codigo)**: The item code often appears surrounded by brackets (e.g., "[26752001]"). You MUST extract the code WITHOUT the brackets. For example, extract "26752001" instead of "[26752001]".
- **Invoice Number (numero)**: The invoice number MUST be exactly the value next to "Numero DTE:". Do NOT extract the "No. Interno:" (e.g., do not extract "FCAM2/2026/00140").
- **Purchase Order (no_pedido)**: The purchase order is often written at the bottom of the table as "* OC VOL 212228 CELASA" or similar. Extract the purchase order number (e.g., "212228" or "VOL-212228"). If you find it anywhere in the document, assign it to `no_pedido`.
- **Other fields**: Follow the standard parsing rules.
