import { Component } from '@angular/core';
import { ToastService, Toast } from '../../services/toast.service';
import { Observable } from 'rxjs';

@Component({
    selector: 'app-toast',
    templateUrl: './toast.component.html',
    styles: []
})
export class ToastComponent {
    toasts$: Observable<Toast[]>;

    constructor(private toastService: ToastService) {
        this.toasts$ = this.toastService.toasts$;
    }

    remove(id: number) {
        this.toastService.remove(id);
    }
}
