import { Component } from '@angular/core';
import { AuthService } from '../services/auth.service';
import { Router } from '@angular/router';
import { ThemeService } from '../services/theme.service';
import { InvoiceReviewService } from '../services/invoice-review.service';

@Component({
    selector: 'app-dashboard',
    templateUrl: './dashboard.component.html',
    styleUrls: ['./dashboard.component.css']
})
export class DashboardComponent {
    user: any;
    // 'archivos' | 'admin'
    activeMode = 'archivos';

    constructor(
        private authService: AuthService, 
        private router: Router,
        public themeService: ThemeService,
        public reviewService: InvoiceReviewService
    ) {
        this.user = this.authService.getUser();
        if (!this.user) {
            this.router.navigate(['/login']);
        }
    }

    toggleTheme() {
        this.themeService.toggleTheme();
    }

    logout() {
        this.authService.logout().subscribe({
            complete: () => {
                this.router.navigate(['/login']);
            },
            error: () => {
                this.router.navigate(['/login']);
            }
        });
    }
}
