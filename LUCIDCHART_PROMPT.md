# AI Prompt for Google Gemini (DFD Generation)

Copy and paste the following prompt into Google Gemini (Advanced/Pro) to generate a detailed Data Flow Diagram (DFD) using Mermaid.js syntax.

---

**Prompt:**

Act as an expert Systems Analyst and Data Architect. I need you to create a detailed Data Flow Diagram (DFD) for an Emergency Response System called "ManResponde".

**System Overview:**
ManResponde is a web and mobile application used by the Municipality of San Carlos. It allows citizens to report emergencies (Ambulance, Police, Fire, Flood, Tanod) via a mobile app. These reports are received by a Command Center Web Dashboard, verified by staff, and dispatched to responders.

**Please generate the DFD using Mermaid.js syntax (graph TD or flowchart LR).**

**1. External Entities (Rectangles):**
*   **Citizen:** The public user reporting emergencies via the mobile app.
*   **Responder:** The field unit (Ambulance, Police, Fire, Tanod) receiving alerts.
*   **Command Center Staff:** The dispatcher monitoring the web dashboard.
*   **System Admin:** The super-user managing accounts and system integrity.

**2. Data Stores (Cylinders/Databases - Firestore Collections):**
*   **users:** Stores user profiles, authentication data, roles, and verification status.
*   **ambulance_reports:** Stores medical emergency incident data.
*   **police_reports:** Stores crime and safety incident data.
*   **fire_reports:** Stores fire incident data.
*   **flood_reports:** Stores flood incident data.
*   **tanod_reports:** Stores local barangay incident data.
*   **other_reports:** Stores miscellaneous incident data.
*   **notifications:** Stores logs of push notifications sent to users and responders.

**3. Processes (Rounded Rectangles):**
*   **1.0 User Management:** Handles registration, login, and ID verification.
*   **2.0 Incident Reporting:** Captures incident details, GPS location, and media evidence. Routes to specific report collections.
*   **3.0 Incident Management & Dispatch:** Allows staff to view, verify, approve, and dispatch responders.
*   **4.0 Notification Service:** Generates real-time alerts via Firebase Cloud Messaging (FCM).

**4. Data Flows (Arrows):**
*   **Citizen** -- "Registration Details" --> **1.0 User Management**
*   **1.0 User Management** -- "Save/Read Profile" --> **users**
*   **Citizen** -- "Emergency Report (Location, Photo, Type)" --> **2.0 Incident Reporting**
*   **2.0 Incident Reporting** -- "Save Data" --> **ambulance_reports** (and other report collections)
*   **ambulance_reports** -- "Pending Reports" --> **3.0 Incident Management & Dispatch**
*   **Command Center Staff** -- "Status Update (Approved/Declined)" --> **3.0 Incident Management & Dispatch**
*   **3.0 Incident Management & Dispatch** -- "Update Status" --> **ambulance_reports**
*   **3.0 Incident Management & Dispatch** -- "Trigger Alert" --> **4.0 Notification Service**
*   **4.0 Notification Service** -- "Log Alert" --> **notifications**
*   **4.0 Notification Service** -- "Push Notification" --> **Responder**
*   **4.0 Notification Service** -- "Status Update" --> **Citizen**

**Instructions:**
1.  Create the Mermaid.js code for this DFD. Use appropriate shapes (rect for entities, rounded rect for processes, cylinder/db for data stores).
2.  After the code, provide a detailed discussion explaining the diagram, covering the roles of processes, significance of data stores, and interaction of entities.

---

# DFD Discussion & Explanation

*(Use this text for your presentation's discussion part)*

**Overview:**
The Data Flow Diagram (DFD) illustrates the logical flow of information within the ManResponde system, highlighting how data is captured from external entities, processed by the system, and stored in our Google Cloud Firestore database.

**1. External Entities:**
*   **Citizens** act as the primary data source, initiating the flow by submitting emergency reports and registration data.
*   **Command Center Staff** serve as the control point, consuming report data to make critical dispatch decisions.
*   **Responders** are the recipients of processed data (alerts), enabling them to act on emergencies.

**2. Processes:**
*   **Process 1.0 (User Management):** This is the gatekeeper of the system. It ensures that only verified users can submit reports, reducing the likelihood of prank calls. It interacts directly with the `users` collection to validate credentials.
*   **Process 2.0 (Incident Reporting):** This process handles the ingestion of high-volume data (images, GPS coordinates) from the mobile app. It intelligently routes the data to the specific report collection (e.g., `fire_reports` vs `police_reports`) based on the incident type selected by the user.
*   **Process 3.0 (Incident Management):** Located at the Command Center Web Dashboard, this process transforms "raw data" (pending reports) into "actionable intelligence" (approved incidents). It allows staff to filter, verify, and update the status of reports.
*   **Process 4.0 (Notification Service):** This is the system's output mechanism. Once a report is approved, this process triggers Firebase Cloud Messaging (FCM) to push real-time alerts to the relevant responders and updates the reporting citizen, ensuring a closed feedback loop.

**3. Data Stores (Firestore Collections):**
*   **`users`:** The central registry for all actors in the system.
*   **Report Collections (`ambulance_reports`, `fire_reports`, etc.):** These are segregated collections optimized for query performance. By separating reports by type, the system ensures that a high volume of flood reports, for example, does not slow down the retrieval of urgent ambulance reports.
*   **`notifications`:** This serves as an audit trail, logging every alert sent by the system to ensure accountability and traceability of communications.

**Relevance to Objectives:**
This architecture ensures **data integrity** through strict user management, **efficiency** through categorized report storage, and **responsiveness** through an automated notification pipeline, directly supporting the project's goal of reducing emergency response times.
