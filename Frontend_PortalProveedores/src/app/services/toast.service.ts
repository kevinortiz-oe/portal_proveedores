import { Injectable } from '@angular/core';
import { BehaviorSubject } from 'rxjs';

export interface Toast {
    id: number;
    message: string;
    type: 'success' | 'error' | 'info' | 'warning';
    duration?: number;
}

@Injectable({
    providedIn: 'root'
})
export class ToastService {
    private toastsSubject = new BehaviorSubject<Toast[]>([]);
    toasts$ = this.toastsSubject.asObservable();
    private idCounter = 0;

    show(message: string, type: 'success' | 'error' | 'info' | 'warning' = 'info', duration: number = 9000) {
        const id = this.idCounter++;
        const newToast: Toast = { id, message, type, duration };

        const currentToasts = this.toastsSubject.getValue();
        this.toastsSubject.next([...currentToasts, newToast]);

        if (duration > 0) {
            setTimeout(() => {
                this.remove(id);
            }, duration);
        }
    }

    remove(id: number) {
        const currentToasts = this.toastsSubject.getValue();
        const updatedToasts = currentToasts.filter(t => t.id !== id);
        this.toastsSubject.next(updatedToasts);
    }
}
