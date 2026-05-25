### AQUASISTEMAS SPECIFIC RULES
- **Purchase Order (no_pedido)**: Do NOT extract "PEDIDO" (e.g., "PEDIDO: S109817") or any internal supplier order numbers as the purchase order. You must ONLY extract the value for `no_pedido` if it is explicitly labeled as "OC", "ORDEN DE COMPRA", or "O/C". If there is no explicit "OC", leave `no_pedido` blank (null) or an empty string.
- **Other fields**: Follow general parsing rules. Ensure `codigo` and `descripcion` are mapped correctly.
