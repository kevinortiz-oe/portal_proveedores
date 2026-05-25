### LUXLITE (ILUMINACION CONTINENTAL) INSTRUCTIONS
This invoice is from "ILUMINACION CONTINENTAL, S.A." (LUXLITE). Please apply the following specific rules:

1. **Serie and Numero**: 
   - You will see text like "FACTURA [SERIE]". Extract the `serie` from here (e.g., "A5766FF5").
   - Right below the serie, there is a large number. This is the `numero` (e.g., "120013898").
2. **Fecha**: 
   - The date is located below the Serie and Numero, usually in a format like "13/Feb/2,026" or "13/02/2026".
   - Extract this date and convert it strictly to `YYYY-MM-DD` format (e.g., "2026-02-13").
3. **OC / Purchase Order — CRITICAL**:
   - In Luxlite invoices, the purchase order number is found in the **"Observaciones:"** field at the bottom of the invoice (not in a column).
   - It appears in the format: `Observaciones: VOL-208320` or `Observaciones: CEL-12345`.
   - Extract this value (e.g., `"VOL-208320"`) into the `no_pedido` field.
   - Also use this SAME value for EVERY item's `oc_detalle` field (e.g., `"VOL-208320"` for all items).
   - **DO NOT use product catalog codes** like `ELC-203NO`, `CT-2115RW`, `LUX-065`, `LUM287` as OC values — these are product SKUs that go in `codigo`, NOT in `oc_detalle`.
   - If the Observaciones field does not contain a VOL- or CEL- number, set `oc_detalle` to `null` for all items.
4. **Product Codes (codigo)**:
   - Product codes are the SKUs shown in the items table (e.g., `LED0428`, `LUM1294`, `LED0113`).
   - **OCR CORRECTION**: The digit "0" (zero) is often misread as the letter "O". If you see an "O" in what appears to be a numeric product code, treat it as "0".
5. **General**: Extract all items, quantities, and prices accurately. Ignore any text inside parentheses in descriptions when assigning `codigo`.

