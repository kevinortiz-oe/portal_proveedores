import { Injectable } from '@angular/core';
import { BehaviorSubject, Subject } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class InvoiceReviewService {
  isOpen$ = new BehaviorSubject<boolean>(false);
  invoice$ = new BehaviorSubject<any>(null);
  isSaving$ = new BehaviorSubject<boolean>(false);
  providerCode$ = new BehaviorSubject<string>('');

  confirmed$ = new Subject<any>();
  cancelled$ = new Subject<void>();

  open(invoice: any, providerCode: string) {
    this.invoice$.next(invoice);
    this.providerCode$.next(providerCode);
    this.isOpen$.next(true);
  }

  close() {
    this.isOpen$.next(false);
    this.invoice$.next(null);
  }

  setSaving(saving: boolean) {
    this.isSaving$.next(saving);
  }

  confirm(invoice: any) {
    this.confirmed$.next(invoice);
  }

  cancel() {
    this.cancelled$.next();
  }
}
