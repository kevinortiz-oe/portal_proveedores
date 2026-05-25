import { Component, EventEmitter, Input, Output, OnChanges, SimpleChanges } from '@angular/core';
import { DomSanitizer, SafeResourceUrl } from '@angular/platform-browser';
import { environment } from '../../../environments/environment';

@Component({
  selector: 'app-invoice-review',
  templateUrl: './invoice-review.component.html',
  styleUrls: ['./invoice-review.component.css']
})
export class InvoiceReviewComponent implements OnChanges {
  @Input() isOpen = false;
  @Input() invoice: any = {};
  @Input() isSubmitting = false;
  @Input() providerCode: string = '';
  showConfirmCancel = false;

  @Output() confirmed = new EventEmitter<any>();
  @Output() cancelled = new EventEmitter<void>();

  pdfUrl: SafeResourceUrl | null = null;
  validationErrors: any[] = [];

  constructor(private sanitizer: DomSanitizer) {}

  ngOnChanges(changes: SimpleChanges) {
    if ((changes['isOpen'] && changes['isOpen'].currentValue) || changes['invoice']) {
      this.showConfirmCancel = false;
      this.formatAllItems();
      this.calculateTotal();
      this.parseValidationErrors();
      this.validateHeaderFields();
      this.revalidateMath();
      this.generatePdfUrl();
    }
  }

  generatePdfUrl() {
    if (this.invoice && this.invoice.pdfStored && this.providerCode) {
      const rawUrl = `${environment.baseUrl}/view-pdf/${this.providerCode}/${this.invoice.pdfStored}`;
      this.pdfUrl = this.sanitizer.bypassSecurityTrustResourceUrl(rawUrl);
    } else {
      this.pdfUrl = null;
    }
  }

  parseValidationErrors() {
    this.validationErrors = [];
    if (this.invoice && this.invoice.errores_validacion) {
      try {
        if (typeof this.invoice.errores_validacion === 'string') {
          this.validationErrors = JSON.parse(this.invoice.errores_validacion);
        } else {
          this.validationErrors = this.invoice.errores_validacion;
        }
      } catch (e) {
        console.error('Error parsing errores_validacion', e);
      }
    }
  }

  /** Valida los campos de cabecera de la factura y agrega errores al array si están vacíos o inválidos */
  validateHeaderFields() {
    // Eliminar errores de cabecera previos para recalcularlos frescos
    const headerFields = ['serie', 'numero', 'fecha', 'uuid', 'moneda', 'tipo_cambio'];
    this.validationErrors = this.validationErrors.filter(
      err => !headerFields.includes(err.campo) || err.item_index !== undefined
    );

    const inv = this.invoice;
    if (!inv) return;

    // Serie
    if (!inv.serie || String(inv.serie).trim() === '') {
      this.validationErrors.push({ campo: 'serie', mensaje: 'La serie es requerida.' });
    }

    // Número / DTE
    if (!inv.numero || String(inv.numero).trim() === '') {
      this.validationErrors.push({ campo: 'numero', mensaje: 'El número de factura es requerido.' });
    }

    // Fecha
    if (!inv.fecha || String(inv.fecha).trim() === '') {
      this.validationErrors.push({ campo: 'fecha', mensaje: 'La fecha de emisión es requerida.' });
    } else {
      const fechaDate = new Date(inv.fecha);
      const now = new Date();
      if (isNaN(fechaDate.getTime())) {
        this.validationErrors.push({ campo: 'fecha', mensaje: 'La fecha de emisión no es válida.' });
      } else if (fechaDate > now) {
        this.validationErrors.push({ campo: 'fecha', mensaje: 'La fecha de emisión no puede ser futura.' });
      }
    }

    // UUID / No. Autorización
    if (!inv.uuid || String(inv.uuid).trim() === '') {
      this.validationErrors.push({ campo: 'uuid', mensaje: 'El UUID/No. de Autorización es requerido.' });
    }

    // Moneda
    const monedasValidas = ['USD', 'GTQ', 'MXN'];
    if (!inv.moneda || !monedasValidas.includes(String(inv.moneda).toUpperCase())) {
      this.validationErrors.push({ campo: 'moneda', mensaje: `Moneda inválida. Se esperaba: ${monedasValidas.join(', ')}.` });
    }

    // Tipo de Cambio: si la moneda no es GTQ, el tipo de cambio debe ser > 0
    const tipoCambio = parseFloat(inv.tipo_cambio);
    if (isNaN(tipoCambio) || tipoCambio <= 0) {
      this.validationErrors.push({ campo: 'tipo_cambio', mensaje: 'El tipo de cambio debe ser mayor a 0.' });
    }
  }

  hasError(field: string): boolean {
    return this.validationErrors.some(err => err.campo === field && err.item_index === undefined);
  }

  hasRowError(index: number, field: string): boolean {
    return this.validationErrors.some(err => err.item_index === index && err.campo === field);
  }

  /** Elimina la alerta de código de una fila cuando el usuario la edita manualmente */
  clearCodeAlert(index: number) {
    this.validationErrors = this.validationErrors.filter(
      err => !(err.item_index === index && err.campo === 'codigo')
    );
  }

  /** Re-valida matemáticamente todas las filas y actualiza los errores visibles */
  revalidateMath() {
    // Eliminar errores matemáticos previos (se recalculan)
    this.validationErrors = this.validationErrors.filter(err => err.campo !== 'importe' && err.campo !== 'subtotal' && err.campo !== 'total');
    // Tolerancia de 0.05 para permitir diferencias de redondeo o correcciones manuales de centavos
    const tolerance = 0.05;
    if (this.invoice.items && Array.isArray(this.invoice.items)) {
      this.invoice.items.forEach((item: any, idx: number) => {
        const qty = this.parseNumber(item.cantidad);
        const price = this.parseNumber(item.valorUnitario);
        const discount = this.parseNumber(item.montoDescuento);
        const tax = this.parseNumber(item.montoImpuesto);
        const importe = this.parseNumber(item.importe);
        const expected = (qty * price);
        if (Math.abs(expected - importe) > tolerance && qty > 0 && price > 0) {
          this.validationErrors.push({
            item_index: idx,
            campo: 'importe',
            mensaje: `Fallo aritmético en la línea ${idx + 1}: (${qty} * ${price}) = ${expected.toFixed(2)}, pero el importe es ${importe.toFixed(2)}.`
          });
        }
      });
    }
  }

  onConfirm() {
    this.confirmed.emit(this.invoice);
  }

  onCancel() {
    this.showConfirmCancel = true;
  }

  confirmCancel() {
    this.showConfirmCancel = false;
    this.cancelled.emit();
  }

  dismissCancel() {
    this.showConfirmCancel = false;
  }

  formatAllItems() {
    if (this.invoice.items && Array.isArray(this.invoice.items)) {
      this.invoice.items.forEach((item: any) => {
        item.cantidad = this.toPrecision(item.cantidad, 0);
        item.valorUnitario = this.toPrecision(item.valorUnitario, 4);
        item.montoDescuento = this.toPrecision(item.montoDescuento, 4);
        item.montoImpuesto = this.toPrecision(item.montoImpuesto, 4);
        item.importe = this.toPrecision(item.importe, 2);
      });
    }
  }

  parseNumber(val: any): number {
    if (typeof val === 'string') {
        return Number(val.replace(/,/g, '') || 0);
    }
    return Number(val || 0);
  }

  toPrecision(val: any, decimals: number): string {
    const num = this.parseNumber(val);
    return num.toLocaleString('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    });
  }

  onBlurFormat(item: any, field: string, decimals: number) {
    if (field === 'cantidad') decimals = 0;
    item[field] = this.toPrecision(item[field], decimals);
    this.onItemFieldChange(item);
  }

  onItemFieldChange(item: any) {
    const qty = this.parseNumber(item.cantidad);
    const price = this.parseNumber(item.valorUnitario);
    const discount = this.parseNumber(item.montoDescuento);
    const tax = this.parseNumber(item.montoImpuesto);
    
    const rawImporte = (qty * price);
    item.importe = this.toPrecision(rawImporte, 2);
    
    this.calculateTotal();
    this.revalidateMath();
  }

  calculateTotal() {
    let sumImporte = 0;
    let sumImpuestos = 0;
    let sumDescuentos = 0;

    if (this.invoice.items && Array.isArray(this.invoice.items)) {
      this.invoice.items.forEach((item: any) => {
        const imp = this.parseNumber(item.importe);
        sumImporte += imp;
        sumImpuestos += this.parseNumber(item.montoImpuesto);
        sumDescuentos += this.parseNumber(item.montoDescuento);
      });
    }

    this.invoice.subtotal = Number(sumImporte.toFixed(2));
    this.invoice.total_impuestos = Number(sumImpuestos.toFixed(2));
    this.invoice.total_descuento = Number(sumDescuentos.toFixed(2));
    this.invoice.total = Number((this.invoice.subtotal + this.invoice.total_impuestos - this.invoice.total_descuento).toFixed(2));
  }
}
