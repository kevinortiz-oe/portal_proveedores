### GENERAL INSTRUCTIONS
This is the default parsing logic for construction material invoices.

1. **Header Identification**: Search for standard column headers like Description, Quantity, Price, and Amount.
2. **Multiple Invoices**: If the text contains more than one invoice, extract each one as a separate object in the `invoices` array.
3. **OC Mapping**: Look for "Orden de Compra" or "OC" and map it to `no_pedido`.
4. **Items**: Ensure each item has a description, quantity, and total amount.
5. **Robustness**: Ignore OCR noise and background artifacts.
