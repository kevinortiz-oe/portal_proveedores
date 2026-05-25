import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Observable } from 'rxjs';
import { AuthService } from './auth.service';
import { environment } from '../../environments/environment';

@Injectable({
    providedIn: 'root'
})
export class InvoiceService {
    private apiUrl = environment.baseUrl;

    constructor(private http: HttpClient, private authService: AuthService) { }

    // 1. Stage File (Upload immediately)
    stageFile(formData: FormData, providerCode: string): Observable<any> {
        formData.append('provider_code', providerCode);
        const user = this.authService.getUser();
        const headers = new HttpHeaders({ 'X-User-Id': user ? user.id.toString() : '0' });

        return this.http.post<any>(`${this.apiUrl}/upload-stage`, formData, { headers });
    }

    // 2. Process Batch (Legacy or direct)
    processBatch(data: any): Observable<any> {
        const user = this.authService.getUser();
        const headers = new HttpHeaders({ 'X-User-Id': user ? user.id.toString() : '0' });

        return this.http.post<any>(`${this.apiUrl}/process-batch`, data, { headers });
    }

    // 3. Analyze Invoice (New)
    analyzeInvoice(data: any): Observable<any> {
        const user = this.authService.getUser();
        const headers = new HttpHeaders({ 'X-User-Id': user ? user.id.toString() : '0' });
        return this.http.post<any>(`${this.apiUrl}/analyze-invoice`, data, { headers });
    }

    // 4. Save Invoice (New)
    saveInvoice(data: any): Observable<any> {
        const user = this.authService.getUser();
        const headers = new HttpHeaders({ 'X-User-Id': user ? user.id.toString() : '0' });
        return this.http.post<any>(`${this.apiUrl}/save-invoice`, data, { headers });
    }

    // 5. Delete Staged Files (Clean up)
    deleteStagedFiles(files: string[], providerCode: string): Observable<any> {
        const user = this.authService.getUser();
        const headers = new HttpHeaders({ 'X-User-Id': user ? user.id.toString() : '0' });

        return this.http.post<any>(`${this.apiUrl}/delete-staged`, {
            provider_code: providerCode,
            files: files
        }, { headers });
    }
}
