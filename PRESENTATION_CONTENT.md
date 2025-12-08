# ManResponde - Slide Presentation Content

## ❖ Company/Organization Background and Contact Person

**Organization:** Municipality of San Carlos - City Disaster Risk Reduction and Management Office (CDRRMO) / Emergency Response Unit.

**Background:**
The Municipality of San Carlos is committed to ensuring the safety and well-being of its citizens. The Emergency Response Unit (CDRRMO) is the primary agency responsible for disaster preparedness, response, and recovery. They handle various emergencies including medical incidents, fires, floods, and public safety concerns. The "ManResponde" system is designed to modernize their operations, moving from manual reporting to a digital, real-time response system.

**Contact Person:**
*   **Name:** [Insert Name of CDRRMO Head or Project Focal Person]
*   **Role:** [Insert Role, e.g., CDRRMO Officer / Head of Emergency Response]
*   **Office:** City Disaster Risk Reduction and Management Office, San Carlos City Hall.

*(Note: Please insert the actual name and role of your contact person here. Include photos of the San Carlos City Hall or the CDRRMO command center to verify the organization.)*

---

## ❖ Issues and Challenges Identified

1.  **Delayed Reporting & Response:** Traditional methods (phone calls/texts) can be slow and prone to miscommunication regarding exact locations.
2.  **Lack of Real-Time Visualization:** Dispatchers lack a centralized, real-time map view of all active incidents and responder locations.
3.  **Verification Difficulties:** Difficulty in verifying the authenticity of reports (prank calls) without visual proof (photos/videos) or identity verification.
4.  **Fragmented Communication:** Coordination between different departments (Police, Fire, Ambulance, Tanod) is often siloed, leading to inefficient resource allocation.
5.  **Manual Data Handling:** Reliance on manual logging of incidents makes generating reports and analyzing data for future planning time-consuming and error-prone.

---

## ❖ Main Objective/Purpose of the System

The primary objective of the **ManResponde** system is to establish a centralized, technology-driven emergency response platform for the Municipality of San Carlos. It aims to bridge the gap between citizens and emergency services by providing a mobile application for rapid, accurate incident reporting and a web-based command center for efficient dispatch and coordination. The system seeks to significantly reduce response times, ensure the authenticity of reports through user verification, and provide data-driven insights to improve overall disaster management and public safety. Ultimately, ManResponde serves to save lives and property by streamlining the entire emergency response lifecycle.

---

## ❖ Scope of the System

The **ManResponde** system consists of the following key modules:

1.  **User Accounts & Verification Management:**
    *   Handles registration and authentication for Admins, Staff, Responders, and Citizens.
    *   Includes an identity verification module (ID upload) to ensure legitimate user accounts and reduce prank reports.

2.  **Emergency Reporting Module (Mobile App):**
    *   Allows citizens to report various incidents (Ambulance, Police, Fire, Flood, Tanod, Other).
    *   Captures real-time location (GPS), photos, and videos as proof of the incident.

3.  **Command Center Dashboard (Web App):**
    *   A centralized interface for Admins and Staff to monitor incoming reports in real-time.
    *   Features categorized views (Ambulance, Police, etc.) and status tracking (Pending, Approved, Declined).

4.  **Responder Dispatch & Coordination:**
    *   Facilitates the assignment of specific responder teams to verified incidents.
    *   Allows responders to receive alerts and update the status of the incident (e.g., "Responding", "Resolved").

5.  **Real-Time Notification System:**
    *   Utilizes Firebase Cloud Messaging (FCM) to send instant alerts to responders and status updates to reporting citizens.
    *   Includes visual and audio alerts on the Command Center Dashboard for new urgent reports.

6.  **Reports & Analytics:**
    *   Generates downloadable reports (PDF/Excel) of incident logs for documentation and analysis.
    *   Provides statistical overviews of incident types and response activities.

---

## ❖ Users of the System

1.  **System Administrator:**
    *   **Role:** Super-user with full control. Manages user accounts (approving/rejecting registrations), configures system settings, and oversees the entire operation.

2.  **Command Center Staff / Dispatcher:**
    *   **Role:** The operational hub. Monitors the dashboard, verifies incoming reports, communicates with citizens, and dispatches the appropriate responder units.

3.  **Responders (Ambulance, Police, Fire, Tanod):**
    *   **Role:** Field units who receive dispatch alerts via the mobile app, proceed to the scene, and provide on-ground assistance. They update the incident status to keep the command center informed.

4.  **Citizens (General Public):**
    *   **Role:** The primary source of information. They use the mobile app to report emergencies, providing location and visual proof to request assistance.

---

## ❖ Stakeholders

1.  **Municipality of San Carlos (LGU):** The primary beneficiary and governing body responsible for public safety.
2.  **CDRRMO / Emergency Response Teams:** The direct operational users who will benefit from improved efficiency.
3.  **Citizens of San Carlos:** The ultimate beneficiaries who receive faster and more reliable emergency services.
4.  **Barangay Officials / Tanods:** Local community peacekeepers integrated into the response network.
5.  **System Developers:** The technical team responsible for maintaining and updating the system.

---

## ❖ Use Cases

1.  **Citizen Reporting a Medical Emergency:** A user logs into the mobile app, selects "Ambulance", takes a photo of the scene, and submits the report. The system automatically captures their GPS location.
2.  **Staff Verifying and Dispatching:** The Command Center Staff receives a "High Priority" alert on the dashboard. They review the photo and location, approve the report, and dispatch the nearest ambulance team.
3.  **Responder Action:** The Ambulance team receives a push notification with the location and details. They acknowledge the alert, proceed to the site, and mark the report as "Resolved" once the patient is attended to.
4.  **Admin Managing Users:** The System Admin reviews a list of new user registrations, checks their uploaded IDs for validity, and approves their accounts, granting them access to report incidents.
5.  **Generating Monthly Reports:** At the end of the month, the Admin exports a summary of all "Fire" incidents to analyze frequency and hotspots for better resource planning.

---

## ❖ Hardware, Software and Service Requirements

**1. Development Requirements:**
*   **Code Editor:** VS Code / Sublime Text
*   **Database:** Google Cloud Firestore (NoSQL)
*   **Programming Languages:** PHP (Backend), JavaScript (Frontend), HTML5, CSS3 (Tailwind CSS).
*   **Platform:** Web (Browser-based) and Mobile (Android/iOS).
*   **Hardware:** Laptop/Desktop (Core i5 or equivalent).

**2. Deployment Requirements:**
*   **Hosting:** Web Server (Apache/Nginx via XAMPP for local, Cloud Hosting for production).
*   **Cloud Services:** Google Firebase (Authentication, Firestore, Storage, Cloud Messaging).
*   **Domain:** Domain Name for the Web Dashboard.

**3. User Access Requirements:**
*   **Command Center:** Desktop Computers or Laptops with Internet Connection.
*   **Responders/Citizens:** Smartphones (Android/iOS) with Mobile Data/Internet and GPS capability.

---

## ❖ Methodology

**Agile Software Development Methodology**
The project follows the Agile methodology, specifically an iterative approach. This allows for continuous feedback and improvement throughout the development lifecycle.

*   **Planning:** Identifying the needs of San Carlos CDRRMO and defining the scope.
*   **Design:** Creating the UI/UX for the Mobile App and Web Dashboard, and designing the Firestore database structure.
*   **Development:** Coding the modules (Auth, Reporting, Dashboard) in sprints.
*   **Testing:** Continuous testing of features (e.g., notification delivery, location accuracy) and user acceptance testing with stakeholders.
*   **Deployment & Maintenance:** Rolling out the system and providing ongoing updates based on user feedback.

*(Note: Include a diagram of the Agile cycle: Plan -> Design -> Develop -> Test -> Deploy -> Review)*

---

## ❖ Flowchart of Existing Processes (Pre-System)

1.  **Start:** Incident occurs.
2.  **Report:** Citizen calls the emergency hotline or texts a number.
3.  **Receive:** Dispatcher answers the call/text.
4.  **Verify:** Dispatcher asks for details (Location, Type, Name). *Bottleneck: Prone to errors, no visual proof, location description can be vague.*
5.  **Dispatch:** Dispatcher calls the responder team via radio/phone.
6.  **Respond:** Responder proceeds to the estimated location. *Challenge: May struggle to find exact spot.*
7.  **Resolve:** Incident handled.
8.  **Log:** Dispatcher manually writes details in a logbook. *Inefficiency: Hard to search and analyze later.*
9.  **End.**

---

## ❖ Entity-Relationship Diagram (ERD)

**Entities:**

1.  **USERS**
    *   **Attributes:** `uid` (PK), `email`, `fullName`, `role` (admin/staff/responder/user), `status` (pending/approved), `fcmToken`, `assignedBarangay`.
    *   **Purpose:** Stores account information for all system actors.

2.  **REPORTS** (Sub-types: Ambulance, Police, Fire, etc.)
    *   **Attributes:** `id` (PK), `reporterId` (FK -> USERS.uid), `location`, `description`, `imageUrl`, `status` (Pending/Approved/Declined), `timestamp`, `priority`.
    *   **Purpose:** Stores details of every emergency incident reported.

3.  **NOTIFICATIONS**
    *   **Attributes:** `id` (PK), `userId` (FK -> USERS.uid), `reportId` (FK -> REPORTS.id), `title`, `message`, `read` (boolean), `timestamp`.
    *   **Purpose:** Tracks alerts sent to users and staff.

**Relationships:**
*   **USERS** (Citizen) *creates* **REPORTS** (1:N).
*   **USERS** (Staff) *manages* **REPORTS** (1:N).
*   **REPORTS** *triggers* **NOTIFICATIONS** (1:N).
*   **USERS** *receives* **NOTIFICATIONS** (1:N).

---

## ❖ Data Flow Diagram (DFD)

**Context Level (Level 0):**
*   **External Entities:** Citizen, Responder, Admin/Staff.
*   **Main Process:** ManResponde System.
*   **Flows:**
    *   Citizen -> Report Details -> System.
    *   System -> Status Update -> Citizen.
    *   System -> Emergency Alert -> Responder.
    *   Responder -> Incident Status -> System.
    *   Admin -> Verification/Dispatch -> System.

**Level 1 DFD Components:**
1.  **Process 1.0: User Management** (Registration, Login, Verification).
2.  **Process 2.0: Incident Reporting** (Submit Report, Upload Proof).
3.  **Process 3.0: Incident Management** (Verify, Approve/Decline, Dispatch).
4.  **Process 4.0: Notification Service** (Send Alerts via FCM).
5.  **Data Stores:** Users DB, Reports DB.

---

## ❖ System Architecture Design

**Structure:**

1.  **Client Layer:**
    *   **Mobile App:** Used by Citizens and Responders (Android/iOS).
    *   **Web Dashboard:** Used by Admin and Staff (Browser-based).

2.  **Application Layer (Backend):**
    *   **PHP Backend:** Handles business logic, API requests, and data processing.
    *   **Firebase Authentication:** Manages secure user login and identity.
    *   **Firebase Cloud Messaging (FCM):** Handles real-time push notifications.

3.  **Data Layer:**
    *   **Google Cloud Firestore:** A flexible, scalable NoSQL database for storing real-time data (Users, Reports).
    *   **Firebase Storage:** Stores media files (Images/Videos of incidents and IDs).

**Interaction:**
*   The **Mobile App** sends report data (JSON) to the **PHP Backend** / **Firestore**.
*   **Firestore** updates trigger real-time listeners on the **Web Dashboard**.
*   **Staff** actions on the Dashboard update **Firestore**, which triggers **FCM** to push notifications back to the **Mobile App**.

*(Note: Create a diagram showing Mobile/Web connecting to the Cloud/Server, with the Database and Storage as the central data repository.)*
