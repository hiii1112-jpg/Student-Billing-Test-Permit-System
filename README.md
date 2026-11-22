Student Billing & Test Permit System

A web-based school billing and examination clearance system developed using Laravel. The system helps manage student records, semester billing, payment transactions, and examination clearance in one application. It allows students and school staff to check and manage billing and clearance information more easily.

What the System Does?
The system connects a student's payment status to their examination eligibility for each exam period.

It uses three clearance statuses:
Green: The required charges for the exam period have been paid.
Yellow: The student has an active promissory note and is allowed to take the exam until the due date.
Red: The student still has an unpaid balance and does not have a valid promissory note.

The system also manages payment records, payment allocations, receipts, promissory notes, exam records, notifications, and activity logs based on each student's school term.

User Roles
The system has five user roles:

Registrar – Manages student records, staff accounts, programs, blocks, subjects, school terms, teacher loads, student imports, and account activation codes.
Head Cashier – Has access to cashier features, reports, payment voiding, and student account holds.
Cashier / Student Assistant – Searches student accounts, views payment ledgers, records cash payments, issues receipts, and processes promissory notes.
Teacher – Manages assigned class loads, views student rosters, and records exam attendance and clearance-related exam statuses.
Student – Activates an account, checks billing and clearance status, submits payment records through the available payment channels, and views statements of account, receipts, and eligible exam permits.
The Head Cashier and Cashier / Student Assistant use the cashier module but have different permissions.

Main Features
Separate dashboards and pages based on user roles
Student account activation using a Certificate of Enrollment (COE) code
Student record management and CSV import
Semester-based billing divided into four payment periods
Cash payment recording
GCash, Maya, and bank payment channel selection and recording
Payment allocation across billing periods
Official receipt generation
Promissory note management
Examination clearance checking
Class offerings, teacher loads, and student rosters
Exam attendance and exam records
Student statements of account and receipts
Student notifications and exam permits
Clearance monitoring and audit logs
Maintenance settings and cashier reports

Payment Note: The system does not have a live connection to GCash, Maya, or any bank. These options are only used to record the selected payment channel in the system. It does not process actual online transactions or require a reference number or proof of payment upload.

Cashier Dashboard
The Cashier Dashboard helps cashiers monitor payment records, daily collections, and active promissory notes.
The dashboard refreshes automatically every 30 seconds to keep the displayed information updated.

It has three main sections:

Overview – Shows the total amount collected today, the number of payments recorded, active promissory notes, and a shortcut to the clearance monitor.
Recent Payments – Displays the 15 most recent payment records, including the date, student name, amount, payment channel, receipt number, and payment status. Cashiers can open a student's ledger or view a receipt from this section.
Prommssory Notes – Shows active promissory notes and their due dates. Overdue notes are highlighted, and cashiers can cancel an active note when necessary.

The Head Cashier also has permission to void verified payments. A reason is required when voiding a payment, and the record is kept for tracking purposes. Regular Cashier / Student Assistant accounts do not have this permission.

GCash, Maya, and Bank are displayed as payment channels only. The system does not directly verify or process payments through these providers.

Technology Used

1.PHP 8.3 or later
2.Laravel 13
3.SQLite (default database)
4.Node.js and npm
5.Vite
6.Tailwind CSS

Local Setup
Run the following commands in PowerShell from the project directory:

composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm install
npm run build
php artisan storage:link
php artisan serve

After running the commands, open the application in your browser:

http://127.0.0.1:8000

Important: The migrate:fresh --seed command deletes existing database tables and recreates them. Use it only when setting up a new local development database or when you are sure the existing data can be deleted.

Seeded Demo Accounts

The database seeder creates the following sample accounts. The default password for the registrar, cashier, teacher, and activated student accounts is password.


| Role | Username | Password |
|---|---|---|
| Registrar | `registrar` | `Ccdi!2026` |
| Head Cashier | `cashier1` | `cashier123` |
| Cashier / Student Assistant | `cashier1` | `cashier234` |
| Teacher | `mrsantos` | `Santos123` |
| Activated Student | `2026-01-0025` | `JeromeAlvarez90` |

The second sample student, 2026-0002, is not yet activated. For a local demonstration, use the COE code COE-DEMO0001 to activate the account and create a password.

The teacher's sample loading code is LD-TEST01.

These accounts and codes are intended for local testing only. Change or remove them before deploying the system.

Running Tests

To run the application's automated tests, use:

php artisan test

Challenges We Faced

Connecting billing and examination clearance: Making sure that payment status, billing periods, promissory notes, and exam eligibility work together was one of the challenging parts.
Managing different user roles: Each user has different responsibilities and permissions, so I had to make sure that users could only access the features allowed for their roles.
Keeping records organized: Payment records, receipts, student accounts, school terms, and exam records are connected. I had to check that updates in one part of the system did not cause problems in another.
Testing the complete process: I needed to check the system from account activation up to payment recording and examination clearance, instead of testing each page separately.

Learning and fixing errors: Developing the system required learning more about Laravel routes, controllers, models, migrations, and Blade views. I also had to spend time debugging and improving the features.

Lessons Learned

It is important to understand the system requirements before starting development.

User roles and permissions should be planned carefully.
Proper database relationships help keep records organized.
Input validation is important, especially when handling student and payment information.
Testing the complete workflow helps find problems that may not appear when checking individual pages.
Building a system takes patience, research, and repeated improvements.

Team Roles and My Contribution

Our team has five members. Each member has a primary area of responsibility, but we also provide support when needed.

Michaella Naga– Project Lead and Main Developer: I handled most of the system development, including the main features, page layouts, application logic, integration, testing, and debugging. I also worked on connecting the different modules so they would function as one complete system.

Gigi Marquez – Requirements and Documentation Support: Helped organize the project requirements, describe system workflows, and prepare project documentation.

Margie Malata – UI/UX and Frontend Support: Helped with screen planning and the appearance of the user interface.

Angel Naga – Database and Backend Support: Provided support related to database structure and application workflows.

Mich Andreana Magtaca – Testing Support: Helped check system features, identify issues, and review expected results.
# Student-Billing-Test-Permit-System
