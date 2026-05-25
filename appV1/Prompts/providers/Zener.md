### PROVIDER 1134 SPECIFIC RULES (CELAFER)
- **Column Mapping**: Ensure you map the following columns precisely:
    - **CÓDIGO/ITEM**: Map to the `codigo` field in the items array. (e.g., "5028N" -> `codigo`).
    - **UM**: Map to the `unidadMedida` field in the items array. (e.g., "Unid" -> `unidadMedida`).
    - **PRECIO/PRICE**: Map to the `valorUnitario` field in the items array. (e.g., "3.18500" -> `valorUnitario`).
    - **IMPORTE/AMOUNT**: Map to the `importe` field in the items array. (e.g., "477.75000" -> `importe`).
    - **REF**: Map to the `oc_detalle` field in the items array. Extract only numeric digits if possible, but keep the core reference.

- **DANGER**: 
    - NEVER use "LN" as a numeric value for quantity; it is just a line number.
    - DO NOT concatenate Quantity and UM into the `codigo` field. The `codigo` field must only contain the value from "CÓDIGO/ITEM".

- **Numeric Format**: 
    - The prices in this invoice might have many decimal places (e.g., 3.18500). Extract them with all precision available.
