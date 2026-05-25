### EAGLE SPECIFIC RULES
- **UUID (uuid)**: For EAGLE invoices, you MUST use the value of **"No. Documento Interno"** (Internal Document Number) as the `uuid` field. This is critical for matching.
- **OC Mapping (no_pedido)**: 
    - **DANGER**: Ignore "Orden de Venta" (Sales Order).
    - **PRIORITY**: If you find "OC" in the "Memo", "Notas", or footer, extract **ONLY THE NUMERIC DIGITS**. (e.g., "OC 208221" -> "208221"). **NEVER include the "OC " prefix.**
- **ITEM PO**: If a general OC is found, propagate it to all items in the `oc_detalle` field as **NUMBERS ONLY**.
- **UOM Mapping (unidadMedida)**: Identify the **"Unidad"** column and map its value (e.g., "Pza", "Unid", "Mts") to the `unidadMedida` JSON field. Ensure this field is populated for every item.
- **Memo Extraction**: Extract the full content of the **"Memo"** section into the `memo` field. This is critical for provider classification.
- **Credit Days (dias_credito)**: Extract the number found in **"Plazo del Crédito"** (e.g., "60" -> 60).
- **Incoterm (termino_compra)**: In the **"Memo"** section, look for the word after **"INCOTERM"** (e.g., "INCOTERM CIP" -> "CIP").


