### NACEL SPECIFIC RULES
- **Identification**: Nacel de Centroamérica invoices usually follow the FEL format.
- **Series & Number (serie, numero)**: 
    - **Serie**: Look for the field explicitly labeled as **"Serie"**. Capture the alphanumeric string. DO NOT use the first part of the UUID/Autorización.
    - **Number (numero)**: Look for **"Número de Documento"** or **"Correlativo"**. Capture the digits.
- **PRODUCT CODE (codigo)**: If you see **"CLAVE PRODUCTO/ SERVICIO"** and **"CODIGO/ ITEM No"** together (like "26121600 3007110"), the code is ONLY the second part (e.g., `3007110`). The first part is a generic SAT category and MUST be ignored.
- **LAYOUT**: Ensure columns are mapped logically even if OCR merges text between them.
