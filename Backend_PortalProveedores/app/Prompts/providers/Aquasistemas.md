### AQUASISTEMAS SPECIFIC RULES
- **Purchase Order (no_pedido)**: Do NOT extract anything from the line "BODEGA DISTRIBUCION PEDIDO:" (e.g., "S109817" or "5109817") or any internal code like "DDC/...". This is an internal supplier tracking number, NOT our purchase order. You must ONLY extract the value for `no_pedido` if it is explicitly labeled as "OC", "ORDEN DE COMPRA", or "O/C". If there is no explicit "OC", you MUST leave `no_pedido` blank (null) or an empty string. DO NOT GUESS.
- **Other fields**: Follow general parsing rules. Ensure `codigo` and `descripcion` are mapped correctly.
