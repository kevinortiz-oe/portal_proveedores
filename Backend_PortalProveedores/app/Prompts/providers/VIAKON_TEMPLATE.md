### VIAKON SPECIFIC RULES
- **LAYOUT**: `PART | NÚMERO IDENTIFICACIÓN | CANTIDAD | U.M. | DESCRIPCIÓN | ... | VALOR UNITARIO | IMPORTE`.
- **PART**: Look for a small number (e.g. 10, 20, 65, 140) followed by the ID. This is a NEW ROW.
- **CODE (codigo)**: Look for **"NÚMERO IDENTIFICACIÓN"** (e.g., `SLZ249`). This is the product code.
- **DANGER (OCR Noise)**: Viakon has a large logo in the background. OCR might inject junk characters like "V", "A", "K" randomly in the middle of rows. **IGNORE THEM**. 
- **MULTILINE**: Description spans multiple lines and ends when you see "TIPO", "CALIBRE" or the next numeric column "VALOR UNITARIO".
