### NACEL SPECIFIC RULES
- **Identification**: Nacel de Centroamérica invoices usually follow the FEL format.
- **Series & Number (serie, numero)**: 
    - **Serie**: Look for the field explicitly labeled as **"Serie"**. Capture the alphanumeric string. DO NOT use the first part of the UUID/Autorización.
    - **Number (numero)**: Look for **"Número de Documento"** or **"Correlativo"**. Capture the digits.
- **PRODUCT CODE (codigo)**: If you see **"CLAVE PRODUCTO/ SERVICIO"** and **"CODIGO/ ITEM No"** together (like "26121600 3007110"), the code is ONLY the second part (e.g., `3007110`). The first part is a generic SAT category and MUST be ignored.
- **Exchange Rate (tipo_cambio)**: Look specifically for the text "Tasa de Cambio: BANGUAT Q.X.XX por 1 USD". You MUST extract the numeric value (e.g., 7.64) and assign it to the `tipo_cambio` field. Do not leave it as 1 if this text is present.
- **LAYOUT**: Ensure columns are mapped logically even if OCR merges text between them.
