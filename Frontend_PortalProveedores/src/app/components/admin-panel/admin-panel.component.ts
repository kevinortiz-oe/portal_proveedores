import { Component, OnInit } from '@angular/core';
import { AuthService } from '../../services/auth.service';

@Component({
    selector: 'app-admin-panel',
    templateUrl: './admin-panel.component.html',
    styleUrls: ['./admin-panel.component.css']
})
export class AdminPanelComponent implements OnInit {
    activeTab = 'usuarios'; // 'usuarios' | 'listaUsuarios' | 'proveedores' | 'listaProveedores'

    // Shared
    providers: any[] = [];
    empresas: any[] = [];

    // ── Lista Usuarios ─────────────────────────────────────────
    userList: any[] = [];
    searchUserTerm: string = '';
    isLoadingUsers = false;
    editingUser: any = null;
    isSavingUser = false;
    saveUserMessage = '';
    saveUserSuccess = false;

    // ── Create User ────────────────────────────────────────────
    isCreatingUser = false;
    createUserMessage = '';
    createUserSuccess = false;
    newUser = { nombreCompleto: '', email: '', password: '', providerCode: '', rol: 'usuario' };

    // ── Lista Proveedores ──────────────────────────────────────
    providerList: any[] = [];
    searchProviderTerm: string = '';
    isLoadingProviders = false;
    editingProvider: any = null;
    isSavingProvider = false;
    saveProviderMessage = '';
    saveProviderSuccess = false;

    // ── Create Provider ────────────────────────────────────────
    isCreatingProvider = false;
    createProviderMessage = '';
    createProviderSuccess = false;
    newProvider = { codigo: '', nombre: '', empresa_compra: null as number | null };

    constructor(private authService: AuthService) { }

    ngOnInit() {
        this.loadProviders();
        this.loadEmpresas();
    }

    // ── LOAD HELPERS ───────────────────────────────────────────
    loadProviders() {
        this.authService.getProviders().subscribe({
            next: (res: any) => { this.providers = res.providers || []; },
            error: () => { }
        });
    }

    loadEmpresas() {
        this.authService.getEmpresas().subscribe({
            next: (res: any) => { this.empresas = res.empresas || []; },
            error: () => { }
        });
    }

    loadUserList() {
        this.isLoadingUsers = true;
        this.authService.getUsers().subscribe({
            next: (res: any) => { this.userList = res.users || []; this.isLoadingUsers = false; },
            error: () => { this.isLoadingUsers = false; }
        });
    }

    loadProviderList() {
        this.isLoadingProviders = true;
        this.authService.getProviders().subscribe({
            next: (res: any) => { this.providerList = res.providers || []; this.isLoadingProviders = false; },
            error: () => { this.isLoadingProviders = false; }
        });
    }

    // ── FILTER GETTERS ─────────────────────────────────────────
    get filteredUserList() {
        if (!this.searchUserTerm) return this.userList;
        const term = this.searchUserTerm.toLowerCase();
        return this.userList.filter(u => 
            (u.nombre_completo || '').toLowerCase().includes(term) ||
            (u.correo || '').toLowerCase().includes(term) ||
            (u.proveedor_codigo || u.proveedor_id?.toString() || '').toLowerCase().includes(term) ||
            (u.rol || '').toLowerCase().includes(term)
        );
    }

    get filteredProviderList() {
        if (!this.searchProviderTerm) return this.providerList;
        const term = this.searchProviderTerm.toLowerCase();
        return this.providerList.filter(p => 
            (p.nombre || '').toLowerCase().includes(term) ||
            (p.codigo || '').toLowerCase().includes(term)
        );
    }

    // ── TAB SWITCHING ──────────────────────────────────────────
    switchTab(tab: string) {
        this.activeTab = tab;
        this.editingUser = null;
        this.editingProvider = null;
        this.searchUserTerm = '';
        this.searchProviderTerm = '';
        if (tab === 'listaUsuarios') this.loadUserList();
        if (tab === 'listaProveedores') this.loadProviderList();
    }

    // ── USER CREATE ────────────────────────────────────────────
    onCreateUser() {
        if (!this.newUser.nombreCompleto || !this.newUser.email || !this.newUser.password || !this.newUser.providerCode) {
            this.createUserMessage = 'Por favor complete todos los campos requeridos';
            this.createUserSuccess = false;
            return;
        }
        this.isCreatingUser = true;
        this.createUserMessage = '';
        this.authService.createUser(this.newUser).subscribe({
            next: (res: any) => {
                this.isCreatingUser = false;
                this.createUserSuccess = true;
                this.createUserMessage = `Usuario "${res.user?.nombre}" creado exitosamente`;
                this.newUser = { nombreCompleto: '', email: '', password: '', providerCode: '', rol: 'usuario' };
            },
            error: (err: any) => {
                this.isCreatingUser = false;
                this.createUserSuccess = false;
                this.createUserMessage = err.error?.messages?.error || 'Error al crear el usuario';
            }
        });
    }

    // ── USER EDIT ──────────────────────────────────────────────
    startEditUser(user: any) {
        this.editingUser = { ...user, password: '' };
        this.saveUserMessage = '';
    }

    cancelEditUser() { this.editingUser = null; }

    saveUser() {
        if (!this.editingUser) return;
        this.isSavingUser = true;
        this.saveUserMessage = '';
        const payload: any = {
            nombre_completo: this.editingUser.nombre_completo,
            correo: this.editingUser.correo,
            rol: this.editingUser.rol,
            activo: this.editingUser.activo,
            proveedor_id: this.editingUser.proveedor_id
        };
        if (this.editingUser.password) payload.password = this.editingUser.password;

        this.authService.updateUser(this.editingUser.id, payload).subscribe({
            next: () => {
                this.isSavingUser = false;
                this.saveUserSuccess = true;
                this.saveUserMessage = 'Usuario guardado correctamente';
                this.loadUserList();
                setTimeout(() => { this.editingUser = null; }, 1200);
            },
            error: (err: any) => {
                this.isSavingUser = false;
                this.saveUserSuccess = false;
                this.saveUserMessage = err.error?.messages?.error || 'Error al guardar';
            }
        });
    }

    // ── PROVIDER CREATE ────────────────────────────────────────
    onCreateProvider() {
        if (!this.newProvider.codigo || !this.newProvider.nombre) {
            this.createProviderMessage = 'El código y nombre del proveedor son requeridos';
            this.createProviderSuccess = false;
            return;
        }
        if (!this.newProvider.empresa_compra) {
            this.createProviderMessage = 'Debe seleccionar una empresa compradora';
            this.createProviderSuccess = false;
            return;
        }
        this.isCreatingProvider = true;
        this.createProviderMessage = '';
        this.authService.createProvider(this.newProvider).subscribe({
            next: (res: any) => {
                this.isCreatingProvider = false;
                this.createProviderSuccess = true;
                this.createProviderMessage = `Proveedor "${res.provider?.nombre}" creado exitosamente`;
                this.newProvider = { codigo: '', nombre: '', empresa_compra: null };
                this.loadProviders();
            },
            error: (err: any) => {
                this.isCreatingProvider = false;
                this.createProviderSuccess = false;
                this.createProviderMessage = err.error?.messages?.error || 'Error al crear el proveedor';
            }
        });
    }

    // ── PROVIDER EDIT ──────────────────────────────────────────
    startEditProvider(prov: any) {
        this.editingProvider = { ...prov };
        this.saveProviderMessage = '';
    }

    cancelEditProvider() { this.editingProvider = null; }

    saveProvider() {
        if (!this.editingProvider) return;
        this.isSavingProvider = true;
        this.saveProviderMessage = '';
        const payload = {
            nombre: this.editingProvider.nombre,
            codigo_proveedor: this.editingProvider.codigo,
            activo: this.editingProvider.activo,
            empresa_compra: this.editingProvider.empresa_compra
        };
        this.authService.updateProvider(this.editingProvider.id, payload).subscribe({
            next: () => {
                this.isSavingProvider = false;
                this.saveProviderSuccess = true;
                this.saveProviderMessage = 'Proveedor guardado correctamente';
                this.loadProviders();
                this.loadProviderList();
                setTimeout(() => { this.editingProvider = null; }, 1200);
            },
            error: (err: any) => {
                this.isSavingProvider = false;
                this.saveProviderSuccess = false;
                this.saveProviderMessage = err.error?.messages?.error || 'Error al guardar';
            }
        });
    }
}
