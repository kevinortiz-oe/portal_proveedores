import { Component, OnInit, OnDestroy } from '@angular/core';
import { InvoiceService } from '../../services/invoice.service';
import { AuthService } from '../../services/auth.service';
import { ToastService } from '../../services/toast.service';
import { InvoiceReviewService } from '../../services/invoice-review.service';
import { firstValueFrom, Subscription } from 'rxjs';

interface FilePair {
    id: string; // unique id (e.g. filename base)
    xml?: File;
    pdf?: File;
    xmlStoredName?: string; // Nombre guardado en servidor
    pdfStoredName?: string; // Nombre guardado en servidor
    status: 'pending' | 'uploading' | 'staged' | 'processing' | 'success' | 'error';
    message?: string;
}

@Component({
    selector: 'app-file-upload',
    templateUrl: './file-upload.component.html',
    styleUrls: ['./file-upload.component.css']
})
export class FileUploadComponent implements OnInit, OnDestroy {
    filePairs: FilePair[] = [];
    isProcessing = false;
    user: any;
    lastInvoiceName = '';
    lastXmlName = '';
    isDraggingPDF = false;

    private currentPairProcessing: FilePair | null = null;
    private processingQueue: FilePair[] = [];
    private subs = new Subscription();

    constructor(
        private invoiceService: InvoiceService,
        private authService: AuthService,
        private toastService: ToastService,
        private reviewService: InvoiceReviewService
    ) {
        this.user = this.authService.getUser();
    }

    ngOnInit(): void {
        // Escuchar cuando el modal confirma o cancela
        this.subs.add(this.reviewService.confirmed$.subscribe(invoice => this.onInvoiceConfirmed(invoice)));
        this.subs.add(this.reviewService.cancelled$.subscribe(() => this.onReviewCancelled()));
    }

    ngOnDestroy(): void {
        this.subs.unsubscribe();
    }

    onDragOver(event: DragEvent, type: string) {
        event.preventDefault();
        event.stopPropagation();
        if (type === 'pdf') this.isDraggingPDF = true;
    }

    onDragLeave(event: DragEvent, type: string) {
        event.preventDefault();
        event.stopPropagation();
        if (type === 'pdf') this.isDraggingPDF = false;
    }

    onDrop(event: DragEvent, type: 'pdf' | 'xml') {
        event.preventDefault();
        event.stopPropagation();
        this.isDraggingPDF = false;

        const files = event.dataTransfer?.files;
        if (files && files.length > 0) {
            for (let i = 0; i < files.length; i++) {
                this.addFile(files[i], type);
            }
        }
    }

    onInvoiceSelected(event: any) {
        try {
            const files: FileList = event.target.files;
            if (files && files.length > 0) {
                for (let i = 0; i < files.length; i++) {
                    this.addFile(files[i], 'pdf');
                }
                event.target.value = '';
                this.lastInvoiceName = '';
            }
        } catch (e) {
            console.error('Error onInvoiceSelected:', e);
            this.toastService.show('Error al leer archivos seleccionados', 'error');
        }
    }

    onXmlSelected(event: any) {
        try {
            const file: File = event.target.files[0];
            if (file) {
                this.addFile(file, 'xml');
                event.target.value = '';
                this.lastXmlName = '';
            }
        } catch (e) {
            console.error('Error onXmlSelected:', e);
            this.toastService.show('Error al leer el archivo XML', 'error');
        }
    }

    addFile(file: File, type: 'pdf' | 'xml') {
        const fileName = file.name;
        const lastDotIndex = fileName.lastIndexOf('.');
        const baseName = lastDotIndex !== -1 ? fileName.substring(0, lastDotIndex) : fileName;

        // Smart Pairing Logic
        let pair = this.filePairs.find(p => p.id === baseName);

        if (!pair) {
            // Find incomplete pair logic
            const incompletePair = [...this.filePairs]
                .reverse()
                .find(p => {
                    if (type === 'pdf') return !p.pdf;
                    if (type === 'xml') return !p.xml;
                    return false;
                });

            if (incompletePair) {
                pair = incompletePair;
            } else {
                pair = { id: baseName, status: 'pending' };
                this.filePairs.push(pair);
            }
        }

        // Assign file locally
        if (type === 'xml') pair.xml = file;
        if (type === 'pdf') pair.pdf = file;

        // UPLOAD IMMEDIATELY (Staging)
        this.uploadToStaging(file, pair, type);
    }

    uploadToStaging(file: File, pair: FilePair, type: 'pdf' | 'xml') {
        const providerCode = this.user?.provider?.code || '';
        const formData = new FormData();
        formData.append('files[]', file);

        pair.status = 'uploading';

        this.invoiceService.stageFile(formData, providerCode).subscribe({
            next: (res: any) => {
                if (res.files && res.files.length > 0) {
                    const stagedFile = res.files[0];
                    if (type === 'xml') pair.xmlStoredName = stagedFile.storedName;
                    if (type === 'pdf') pair.pdfStoredName = stagedFile.storedName;

                    pair.status = 'staged';
                    this.toastService.show(`Archivo ${type.toUpperCase()} listo`, 'success');
                } else {
                    pair.status = 'error';
                    pair.message = res.message || 'El servidor no pudo guardar el archivo temporalmente.';
                    this.toastService.show(`Error preparando ${type.toUpperCase()}`, 'error');
                }
            },
            error: (err) => {
                console.error('Staging error', err);
                pair.status = 'error';
                pair.message = err.error?.message || 'Error de conexión o archivo demasiado pesado';
                this.toastService.show(`Error subiendo ${type.toUpperCase()}`, 'error');
            }
        });
    }

    removePair(pair: FilePair) {
        // Si el par tiene archivos subidos (staged), eliminarlos del servidor
        if (pair.status === 'staged' || pair.status === 'uploading') {
            const filesToDelete = [];
            if (pair.xmlStoredName) filesToDelete.push(pair.xmlStoredName);
            if (pair.pdfStoredName) filesToDelete.push(pair.pdfStoredName);

            if (filesToDelete.length > 0) {
                const providerCode = this.user?.provider?.code || '';
                this.invoiceService.deleteStagedFiles(filesToDelete, providerCode).subscribe({
                    next: () => console.log('Archivos eliminados de staging'),
                    error: (err) => console.error('Error eliminando archivos', err)
                });
            }
        }

        this.filePairs = this.filePairs.filter(p => p !== pair);
    }

    async processAll() {
        const readyPairs = this.filePairs.filter(p => (p.status === 'staged' || p.status === 'pending') && (p.xmlStoredName || p.pdfStoredName));

        if (readyPairs.length === 0) {
            this.toastService.show('No hay archivos listos para procesar.', 'warning');
            return;
        }

        this.isProcessing = true;
        this.processingQueue = [...readyPairs];
        this.processNextInQueue();
    }

    private async processNextInQueue() {
        if (this.processingQueue.length === 0) {
            this.isProcessing = false;
            const remainingErrors = this.filePairs.filter(p => p.status === 'error').length;
            if (remainingErrors === 0) {
                this.toastService.show('Todas las facturas procesadas exitosamente', 'success');
            } else {
                this.toastService.show(`Procesamiento finalizado con ${remainingErrors} errores`, 'warning');
            }
            return;
        }

        const pair = this.processingQueue.shift()!;
        this.currentPairProcessing = pair;
        pair.status = 'processing';

        const payload = {
            provider_code: this.user?.provider?.code || '',
            file: {
                xml: pair.xmlStoredName,
                pdf: pair.pdfStoredName,
                xmlOriginal: pair.xml?.name,
                pdfOriginal: pair.pdf?.name
            }
        };

        try {
            const res: any = await firstValueFrom(this.invoiceService.analyzeInvoice(payload));
            if (res.status === 200 && res.invoices && res.invoices.length > 0) {
                // Abrir el modal de revisión via el servicio compartido
                this.reviewService.open(res.invoices[0], this.user?.provider?.code || '');
            } else {
                pair.status = 'error';
                pair.message = 'No se encontró información en el archivo';
                this.processNextInQueue();
            }
        } catch (err: any) {
            console.error('Analyze error', err);
            pair.status = 'error';
            pair.message = err.error?.message || 'Error analizando archivo';
            this.processNextInQueue();
        }
    }

    async onInvoiceConfirmed(editedInvoice: any) {
        if (!this.currentPairProcessing) return;

        this.reviewService.setSaving(true);
        const payload = {
            provider_code: this.user?.provider?.code || '',
            invoice: editedInvoice
        };

        try {
            const res: any = await firstValueFrom(this.invoiceService.saveInvoice(payload));
            if (res.status === 200 || res.status === 201) {
                this.toastService.show('Factura guardada correctamente', 'success');
                
                // Remove successful pair from list (more robust removal)
                if (this.currentPairProcessing) {
                    const index = this.filePairs.indexOf(this.currentPairProcessing);
                    if (index > -1) {
                        this.filePairs.splice(index, 1);
                    }
                    this.currentPairProcessing = null;
                }
                
                this.closeReview();
                this.processNextInQueue();
            } else {
                this.toastService.show('Error al guardar factura', 'error');
            }
        } catch (err: any) {
            console.error('Save error', err);
            const errorMsg = err.error?.messages?.error || err.error?.message || 'Error de conexión con el servidor';
            this.toastService.show(errorMsg, 'error');
        } finally {
            this.reviewService.setSaving(false);
        }
    }

    onReviewCancelled() {
        if (this.currentPairProcessing) {
            this.currentPairProcessing.status = 'pending';
        }
        this.processingQueue = []; // Detener la cola de procesamiento
        this.isProcessing = false;
        this.closeReview();
        this.toastService.show('Procesamiento cancelado por el usuario', 'warning');
    }

    private closeReview() {
        this.reviewService.close();
    }
}
