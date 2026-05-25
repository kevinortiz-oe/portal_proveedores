import { environment } from '../../environments/environment';
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, tap } from 'rxjs';

@Injectable({
    providedIn: 'root'
})
export class AuthService {
    private apiUrl = environment.baseUrl;
    private tokenKey = 'auth_token';
    private userKey = 'auth_user';

    constructor(private http: HttpClient) { }

    login(email: string, password: string, providerCode: string): Observable<any> {
        return this.http.post<any>(`${this.apiUrl}/login`, { email, password, providerCode })
            .pipe(
                tap(response => {
                    if (response.token) {
                        this.setSession(response);
                    }
                })
            );
    }

    private setSession(authResult: any) {
        localStorage.setItem(this.tokenKey, authResult.token);
        localStorage.setItem(this.userKey, JSON.stringify(authResult.user));
    }

    logout(): Observable<any> {
        const token = this.getToken();
        // Clear local storage first
        localStorage.removeItem(this.tokenKey);
        localStorage.removeItem(this.userKey);

        // Notify backend if we have a token
        if (token) {
            return this.http.post<any>(`${this.apiUrl}/logout`, { token });
        }
        return new Observable(observer => observer.complete());
    }

    isLoggedIn(): boolean {
        return !!localStorage.getItem(this.tokenKey);
    }

    getToken(): string | null {
        return localStorage.getItem(this.tokenKey);
    }

    getUser(): any {
        const user = localStorage.getItem(this.userKey);
        return user ? JSON.parse(user) : null;
    }

    // ===== Admin Methods =====
    getProviders(): Observable<any> {
        return this.http.get<any>(`${this.apiUrl}/providers`);
    }

    createUser(userData: any): Observable<any> {
        return this.http.post<any>(`${this.apiUrl}/users`, userData);
    }

    createProvider(providerData: any): Observable<any> {
        return this.http.post<any>(`${this.apiUrl}/providers`, providerData);
    }

    getEmpresas(): Observable<any> {
        return this.http.get<any>(`${this.apiUrl}/empresas`);
    }

    getUsers(): Observable<any> {
        return this.http.get<any>(`${this.apiUrl}/users`);
    }

    updateUser(id: number, data: any): Observable<any> {
        return this.http.put<any>(`${this.apiUrl}/users/${id}`, data);
    }

    updateProvider(id: number, data: any): Observable<any> {
        return this.http.put<any>(`${this.apiUrl}/providers/${id}`, data);
    }
}
