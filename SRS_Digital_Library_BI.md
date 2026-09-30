\# 1\. System Overview & Tech Stack Context

The Digital Library (DL) system is designed to replace the legacy "Cyber Library" currently used by the Bank Indonesia Institute (BINS). The legacy system is highly unstable, obsolete (dependent on Internet Explorer), lacks modern security controls, and fragments services across multiple isolated applications. The proposed DL aims to centralize physical and digital library management, provide a responsive user interface, and establish a modern architecture capable of future integrations.

\*\*Required Technology Stack:\*\*  
\*   \*\*Backend:\*\* PHP  
\*   \*\*Database:\*\* MySQL  
\*   \*\*Local Environment / Server:\*\* XAMPP, Apache  
\*   \*\*Frontend:\*\* HTML, CSS, JavaScript (Responsive Web Design)  
\*   \*\*Architectural Mandates:\*\* Must support future Single Sign-On (SSO) integration and automated synchronization with the internal HRIS system using Employee ID (NIP) as the primary identifier.

\# 2\. Actors & Permissions

Based on the Use Case analysis, the system enforces strict Role-Based Access Control (RBAC) across three primary actor types:

\*   \*\*Pustakawan (Librarian/Admin):\*\* Requires authentication. Has full administrative privileges including Circulation (borrow/return/extend), Acquisition, Catalog Management (OPAC CRUD), E-Resources links management, News Clipping uploads, Member Data verification, and Report Generation.  
\*   \*\*Anggota (Internal Member/Pegawai BI):\*\* Requires authentication. Can reserve physical books (max 3 books, 14 days), propose new collections, access internal digital collections, access external E-Resources, and read news clippings.  
\*   \*\*Eksternal (Guest/Public):\*\* Can access the landing page and read News Clippings without authentication. Must undergo a separate login/registration process to access specific digital collections. Restricted entirely from physical book reservations and E-Resources.

\# 3\. Functional & Non-Functional Requirements

\*\*Functional Requirements (FR):\*\*  
\*   \*\*FR1 (Authentication):\*\* Secure login and session termination for all roles.   
\*   \*\*FR2 (Reservation):\*\* Members can reserve physical books. If out of stock, members can opt into a Waiting List.  
\*   \*\*FR3 (Circulation):\*\* System must process borrow, return, and extend transactions by validating Member ID and Book Barcode.  
\*   \*\*FR4 (Acquisition & OPAC):\*\* Members can submit book proposals. Admins can approve proposals and manage the main library catalog metadata.  
\*   \*\*FR5 (Digital Assets):\*\* System must serve PDF/digital files securely.  
\*   \*\*FR6 (E-Resources):\*\* System acts as a directory for external journals/databases, tracking user analytics (First login, activity).  
\*   \*\*FR7 (News Clippings):\*\* Admins upload scanned daily clippings; system validates file format. Users can search and read clippings.

\*\*Non-Functional Requirements (NFR):\*\*  
\*   \*\*NFR1 (Responsive Design):\*\* Interface must adapt to desktop and mobile environments.  
\*   \*\*NFR2 (Security):\*\* System must resolve legacy vulnerabilities, sanitize inputs, and prevent unauthorized access to digital assets.  
\*   \*\*NFR3 (Data Integrity):\*\* Implementation of real-time validation for physical book location and availability status.

\# 4\. Core User Journeys

\*\*Journey 1: Physical Book Reservation (Anggota)\*\*  
1\. User navigates to the Reservation Catalog.  
2\. User searches by keyword and selects a title.  
3\. System checks \`available\_stock\` in the database.  
4\. \*\*Branch A (In Stock):\*\* System reserves the item, decrements stock, and generates a unique Reservation Code.  
5\. \*\*Branch B (Out of Stock):\*\* System prompts the user to join a Waiting List. If accepted, the user's ID and timestamp are appended to the waiting list table.

\*\*Journey 2: Circulation Management (Pustakawan)\*\*  
1\. Admin selects Borrow, Return, or Extend.  
2\. Admin inputs \`Member ID\` and physical \`Book Barcode\`.  
3\. System validates if the Member is active and if the Barcode exists/is available.  
4\. System updates the physical item status (e.g., Available \-\> Borrowed) and inserts a circulation log entry.  
5\. System displays transaction success.

\*\*Journey 3: Digital Asset Access (Anggota/Eksternal)\*\*  
1\. User navigates to the Digital Collection menu.  
2\. System queries and returns available digital archives.  
3\. User selects a document. System validates user session and permissions.  
4\. System retrieves the file path from the server storage and renders the PDF.

\# 5\. Proposed Database Schema

\*Analyst Note to AI Agent:\* Implement the following normalized relational schema to support the defined UML logic.

\`\`\`sql  
\-- Core Users & RBAC  
CREATE TABLE users (  
    id INT AUTO\_INCREMENT PRIMARY KEY,  
    nip VARCHAR(50) UNIQUE NULL, \-- Nullable for Eksternal  
    email VARCHAR(100) UNIQUE NOT NULL,  
    password\_hash VARCHAR(255) NOT NULL,  
    role ENUM('anggota', 'pustakawan', 'eksternal') NOT NULL,  
    status ENUM('active', 'inactive', 'pending') DEFAULT 'active',  
    created\_at TIMESTAMP DEFAULT CURRENT\_TIMESTAMP  
);

\-- Catalog (Metadata)  
CREATE TABLE catalog\_books (  
    id INT AUTO\_INCREMENT PRIMARY KEY,  
    title VARCHAR(255) NOT NULL,  
    author VARCHAR(255),  
    publisher VARCHAR(255),  
    publication\_year INT,  
    isbn VARCHAR(50),  
    udc\_classification VARCHAR(100),  
    type ENUM('physical', 'digital') NOT NULL,  
    digital\_file\_path VARCHAR(255) NULL  
);

\-- Physical Items (Inventory)  
CREATE TABLE book\_items (  
    id INT AUTO\_INCREMENT PRIMARY KEY,  
    catalog\_id INT,  
    barcode VARCHAR(100) UNIQUE NOT NULL,  
    shelf\_location VARCHAR(100),  
    status ENUM('available', 'reserved', 'borrowed', 'lost', 'maintenance') DEFAULT 'available',  
    FOREIGN KEY (catalog\_id) REFERENCES catalog\_books(id)  
);

\-- Reservations & Waiting List  
CREATE TABLE reservations (  
    id INT AUTO\_INCREMENT PRIMARY KEY,  
    user\_id INT,  
    catalog\_id INT,  
    reservation\_code VARCHAR(50) UNIQUE,  
    status ENUM('pending\_pickup', 'completed', 'cancelled', 'waiting\_list') DEFAULT 'pending\_pickup',  
    created\_at TIMESTAMP DEFAULT CURRENT\_TIMESTAMP,  
    FOREIGN KEY (user\_id) REFERENCES users(id),  
    FOREIGN KEY (catalog\_id) REFERENCES catalog\_books(id)  
);

\-- Circulation (Borrowing/Returning)  
CREATE TABLE circulations (  
    id INT AUTO\_INCREMENT PRIMARY KEY,  
    user\_id INT,  
    book\_item\_id INT,  
    borrow\_date DATE NOT NULL,  
    due\_date DATE NOT NULL,  
    return\_date DATE NULL,  
    status ENUM('active', 'returned', 'overdue') DEFAULT 'active',  
    fine\_amount DECIMAL(10,2) DEFAULT 0.00,  
    FOREIGN KEY (user\_id) REFERENCES users(id),  
    FOREIGN KEY (book\_item\_id) REFERENCES book\_items(id)  
);

\-- Modules  
CREATE TABLE book\_proposals (  
    id INT AUTO\_INCREMENT PRIMARY KEY,  
    user\_id INT,  
    title VARCHAR(255) NOT NULL,  
    author VARCHAR(255),  
    status ENUM('submitted', 'reviewed', 'approved', 'rejected') DEFAULT 'submitted',  
    FOREIGN KEY (user\_id) REFERENCES users(id)  
);

CREATE TABLE news\_clippings (  
    id INT AUTO\_INCREMENT PRIMARY KEY,  
    title VARCHAR(255) NOT NULL,  
    source\_media VARCHAR(100),  
    publish\_date DATE,  
    file\_path VARCHAR(255) NOT NULL,  
    uploaded\_by INT,  
    FOREIGN KEY (uploaded\_by) REFERENCES users(id)  
);

CREATE TABLE e\_resources (  
    id INT AUTO\_INCREMENT PRIMARY KEY,  
    title VARCHAR(255) NOT NULL,  
    url\_link TEXT NOT NULL,  
    description TEXT  
);  
\`\`\`

\# 6\. Identified Loopholes & Proposed Fixes

\*\*Loophole 1: Missing Waiting List Resolution Logic\*\*  
\*   \*\*Flaw:\*\* The document outlines a flow for users joining a waiting list if a book is out of stock (UC-02). However, it completely omits the systemic trigger for fulfilling this list. How does a user get the book once it is returned?  
\*   \*\*Technical Fix:\*\* Implement an event listener in the \`Circulation\` controller. When a Pustakawan processes a "Return" (\`status \= 'returned'\`), the system must immediately query the \`reservations\` table for \`status \= 'waiting\_list'\` for that specific \`catalog\_id\`, order by \`created\_at ASC\`. If a match is found, automatically create a reservation for the first user in line, update the physical item status to \`reserved\`, and trigger an email/dashboard notification to the user.

\*\*Loophole 2: Concurrency & Race Conditions in Book Reservation\*\*  
\*   \*\*Flaw:\*\* The Activity Diagram for Reservation checks stock availability sequentially before recording the reservation. In a web environment, concurrent requests for the last available book will result in over-booking.  
\*   \*\*Technical Fix:\*\* Utilize Database Transactions with row-level locking. When executing the stock check, the AI agent must use \`SELECT ... FOR UPDATE\` on the \`book\_items\` table to lock the specific physical copy until the \`reservations\` \`INSERT\` statement commits. 

\*\*Loophole 3: E-Resource "Authentication" Illusion\*\*  
\*   \*\*Flaw:\*\* UC-05 allows members to access external E-Resources (like paid journals). The document assumes providing a link from the internal DL is sufficient. It is not; external journals require institutional credential handshakes (EZproxy, OpenAthens, or SAML).  
\*   \*\*Technical Fix:\*\* As a baseline for this build, ensure the \`e\_resources\` links route through a dedicated redirect controller in PHP. This controller will log the "Activity User" analytics required by UC-10 before passing the user to the external URL. Note in the codebase that a proxy server integration is required for actual payload authorization.

\*\*Loophole 4: "Fines" (Denda) Referenced but Undesigned\*\*  
\*   \*\*Flaw:\*\* UC-07 explicitly mentions "pencatatan denda buku" (recording book fines), but neither the sequence diagrams nor the activity diagrams process financial tracking or fine calculation.  
\*   \*\*Technical Fix:\*\* Added a \`fine\_amount\` column to the proposed \`circulations\` schema. The AI agent must implement logic in the Return controller: calculate \`DateDiff(return\_date, due\_date)\`. If it is \> 0, multiply by the institutional daily fine rate and update the \`fine\_amount\`.

\*\*Loophole 5: Ambiguous External User Registration\*\*  
\*   \*\*Flaw:\*\* UC-04 requires Eksternal users to login to access digital collections, yet there is no Use Case defining how Eksternal users get accounts (since they do not have HRIS NIPs).  
\*   \*\*Technical Fix:\*\* Create a dedicated public registration endpoint specifically for role \`eksternal\`. This must include email verification and default the account to \`status \= 'pending'\`. The Pustakawan must manually approve these accounts via the "Kelola Data Keanggotaan" dashboard (UC-12) before they can access digital collections.