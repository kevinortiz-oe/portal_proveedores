### AMANCO SPECIFIC RULES
- **Identification**: Amanco invoices (MEXICHEM GUATEMALA) usually have the AMANCO/WAVIN logo and mention "MEXICHEM GUATEMALA".
- **NIT Emisor (nit_emisor)**: Extract the NIT found in the header, usually labeled as **"NIT. 90950-5"**.
- **Series (serie)**: 
    - Found in the "FACTURA CAMBIARIA" box as **"Serie:"**. 
    - **DANGER (OCR NOISE)**: OCR frequently misreads '0' (zero) as 'O' (letter). If the serie looks like a hexadecimal code (like `7C0CFB95`), ensure you use **'0' (zero)** and NOT 'O'. 
    - **Verification**: The Serie is almost always the first block of the "AUTORIZACIÓN FEL" / UUID. Check there to confirm the correct characters.
- **Number (numero)**: Found as **"No.Doc:"**.
- **OC Mapping**: Look for **"ORDEN COMPRA"** (e.g., "VOL-211193").
