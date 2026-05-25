import { NgModule } from '@angular/core';
import { BrowserModule } from '@angular/platform-browser';
import { HttpClientModule } from '@angular/common/http';
import { FormsModule } from '@angular/forms';

import { AppRoutingModule } from './app-routing.module';
import { AppComponent } from './app.component';
import { DashboardComponent } from './dashboard/dashboard.component';
import { LoginComponent } from './login/login.component';
import { ToastComponent } from './components/toast/toast.component';
import { FileUploadComponent } from './components/file-upload/file-upload.component';
import { AdminPanelComponent } from './components/admin-panel/admin-panel.component';
import { InvoiceReviewComponent } from './components/invoice-review/invoice-review.component';

@NgModule({
  declarations: [
    AppComponent,
    LoginComponent,
    DashboardComponent,
    ToastComponent,
    FileUploadComponent,
    AdminPanelComponent,
    InvoiceReviewComponent
  ],
  imports: [
    BrowserModule,
    AppRoutingModule,
    HttpClientModule,
    FormsModule
  ],
  providers: [],
  bootstrap: [AppComponent]
})
export class AppModule { }
