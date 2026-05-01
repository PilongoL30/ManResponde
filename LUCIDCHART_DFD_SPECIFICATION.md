# LucidChart DFD Specification for MAN-RESPONDE System
## Smart Incident Reporting System with GPS Tracking and AI Chatbot

### DFD Structure Overview

```
EXTERNAL ENTITIES (D1-D4):
├── D1: Users (Citizens, Admin, Staff, Responders)
├── D2: Data Stores (Firestore Collections)
├── D3: Support Chats Database
└── D4: Notifications Database

PROCESSES (0.0 - 5.0):
├── 0.0: MAN-RESPONDE Central System
├── 1.0: User Authentication & Management
├── 2.0: Incident Reporting & Validation
├── 3.0: Incident Management & Dispatch
├── 4.0: Notification Services
└── 5.0: Analytics & Reporting
```

---

## DETAILED DFD COMPONENTS

### 1. EXTERNAL ENTITIES

#### D1: USERS (Data Store - Red Box)
```
Position: Top Center
Box Color: Red (#FF6B6B)
Contents:
├── Citizens
├── Admin
├── Staff/Dispatcher
└── Responders
```

#### D2: FIRESTORE DATABASE (Data Store - Red Box)
```
Position: Bottom Left
Box Color: Red (#FF6B6B)
Contents:
├── tanod_reports
├── fire_reports
├── medical_reports
├── traffic_reports
├── disaster_reports
├── crime_reports
├── users
└── notifications_log
```

#### D3: SUPPORT CHATS (Data Store - Red Box)
```
Position: Bottom Right
Box Color: Red (#FF6B6B)
Contents:
├── support_conversations
├── chatbot_interactions
└── chat_history
```

#### D4: NOTIFICATIONS (Data Store - Red Box)
```
Position: Top Right
Box Color: Red (#FF6B6B)
Contents:
├── FCM_tokens
├── notification_queue
└── delivery_status
```

---

### 2. PROCESSES (Main Flow - Green Boxes)

#### 0.0: MAN-RESPONDE Central System (Green Box - MAIN CENTER)
```
Position: Center
Box Color: Green (#51CF66)
Label: "0.0\nMAN-RESPONDE:\nA Smart Incident\nReporting System\nwith GPS Tracking\nand AI Chatbot"

Inputs from:
├── 1.0 (User Management)
├── 2.0 (Incident Reporting)
├── 3.0 (Incident Management)
├── 4.0 (Notification Services)
└── 5.0 (Analytics)

This is the central hub connecting all processes
```

#### 1.0: User Authentication & Management (Green Box - LEFT UPPER)
```
Position: Upper Left
Box Color: Green (#51CF66)
Label: "1.0\nUser\nAuthentication &\nManagement"

Functions:
├── User Login/Register
├── Verify User Identity
├── Manage User Roles
├── Update User Status
├── Set Custom Claims

Data Flows:
├── Input: D1 Users (credentials)
├── Input: Firebase Auth
├── Output: D1 Users (verified)
└── Output: Session Tokens
```

#### 2.0: Incident Reporting (Green Box - CENTER)
```
Position: Center Upper
Box Color: Green (#51CF66)
Label: "2.0\nIncident\nReporting &\nValidation"

Functions:
├── Receive Report Data
├── Validate Form
├── Capture GPS Location
├── Categorize Incident
├── Store Report

Data Flows:
├── Input: Citizens/Mobile App (GPS + Form)
├── Process: Data Validation
├── Store: D2 Firestore
├── Output: Report ID + Confirmation
└── Trigger: Process 3.0
```

#### 3.0: Incident Management & Dispatch (Green Box - CENTER LOWER)
```
Position: Center Lower
Box Color: Green (#51CF66)
Label: "3.0\nIncident\nManagement &\nDispatch"

Functions:
├── Process New Report
├── Assign Priority
├── Assign Responders
├── Track Status Changes
├── Update Incident Status

Data Flows:
├── Input: D2 (Reports)
├── Input: Staff (Dispatch Decision)
├── Output: Responder Assignment
├── Output: D2 (Updated Status)
└── Trigger: Process 4.0
```

#### 4.0: Notification Services (Green Box - RIGHT MIDDLE)
```
Position: Right Middle
Box Color: Green (#51CF66)
Label: "4.0\nNotification\nServices"

Functions:
├── Generate Notifications
├── Send FCM Push Alerts
├── Send Email/SMS
├── Queue Notifications
├── Log Notification Status

Data Flows:
├── Input: Process 3.0 (Dispatch)
├── Input: D4 (FCM Tokens)
├── Output: D4 (Delivery Status)
├── Output: Responders (Push Alerts)
└── Output: D3 (Support Chats)
```

#### 5.0: Analytics & Reporting (Green Box - UPPER RIGHT)
```
Position: Upper Right
Box Color: Green (#51CF66)
Label: "5.0\nAnalytics &\nReporting"

Functions:
├── Aggregate Statistics
├── Generate Reports
├── Calculate Metrics
├── Create Visualizations
├── Export Data

Data Flows:
├── Input: D2 (All Collections)
├── Input: Admin (Request)
├── Output: Dashboard Data
├── Output: Report Files
└── Output: Analytics Charts
```

---

### 3. DATA FLOWS (Arrows with Labels)

#### User Authentication Flow
```
D1 Users → "Login/Register Credentials" → 1.0 User Auth
1.0 User Auth → "Verified Credentials + Token" → D1 Users
1.0 User Auth → "Auth Status" → 0.0 Central System
```

#### Incident Report Flow
```
Residents/Citizens → "Report Form + GPS Data" → 2.0 Incident Reporting
2.0 Incident Reporting → "Validate Incident Report" → 0.0 Central System
2.0 Incident Reporting → "Store Report Data" → D2 Firestore
```

#### Dispatch Flow
```
Admin/Staff → "Incident Assignment" → 3.0 Incident Management
3.0 Incident Management → "Assign Responder" → Responder
3.0 Incident Management → "Update Status" → D2 Firestore
```

#### Notification Flow
```
3.0 Incident Management → "Alert Trigger" → 4.0 Notification Services
4.0 Notification Services → "Send Push Alert (FCM)" → D4 Notifications
4.0 Notification Services → "Deliver to Mobile" → Responder
D4 Notifications → "Notification Status" → 4.0 Notification Services
```

#### Support/Chatbot Flow
```
Residents/Citizens → "Chat Query" → 4.0 Notification Services
4.0 Notification Services → "Process Chatbot Query" → 0.0 Central System
0.0 Central System → "Gemini AI Response" → D3 Support Chats
D3 Support Chats → "Return Response" → Residents/Citizens
```

#### Analytics Flow
```
D2 Firestore → "Aggregated Data" → 5.0 Analytics & Reporting
5.0 Analytics & Reporting → "Generate Dashboard" → Admin
Admin → "Request Report" → 5.0 Analytics & Reporting
```

---

### 4. COMPLETE DATA STORE DEFINITIONS

#### D2: Firestore Collections (Red Box)
```
ambulance_reports
├── reportID
├── timestamp
├── GPS location
├── status
└── responderID

fire_reports
├── reportID
├── timestamp
├── GPS location
├── status
└── severity_level

police_reports
├── reportID
├── description
├── location
├── status
└── evidence_photos

tanod_reports
├── reportID
├── incident_type
├── location
├── status
└── witnesses

disaster_reports
├── reportID
├── type (flood, earthquake, etc)
├── affected_area
├── status
└── resources_needed

medical_reports
├── reportID
├── patient_info
├── symptoms
├── GPS_location
└── status

crime_reports
├── reportID
├── type
├── location
├── description
└── status

others_reports
├── reportID
├── type
├── details
├── location
└── status

users
├── userID
├── name
├── email
├── role (admin/staff/responder/citizen)
├── verification_status
└── custom_claims

notifications_log
├── notificationID
├── userID
├── message
├── timestamp
└── delivery_status
```

#### D3: Support Chats (Red Box)
```
support_conversations
├── conversationID
├── userID
├── messages
├── timestamp
└── resolved_status

chatbot_interactions
├── interactionID
├── query
├── response
├── timestamp
└── usefulness_rating
```

#### D4: Notifications Queue (Red Box)
```
FCM_tokens
├── userID
├── device_token
├── device_type
└── registration_time

notification_queue
├── notificationID
├── recipientID
├── message
├── priority
└── scheduled_time

delivery_status
├── notificationID
├── status (sent/delivered/failed)
└── timestamp
```

---

### 5. USER TYPES IN SYSTEM

#### CITIZEN/RESIDENT (Yellow Box - LEFT)
```
Position: Left Side
Box Color: Yellow (#FFE066)

Actions:
→ Reports incident
→ Provides GPS location
→ Uploads photos/media
→ Receives updates
→ Chats with AI Bot
→ Tracks status
```

#### ADMIN (Yellow Box - TOP LEFT)
```
Position: Top Left
Box Color: Yellow (#FFE066)

Actions:
→ Logs in
→ Verifies users
→ Views analytics
→ Manages system
→ Generates reports
→ Configures settings
```

#### STAFF/DISPATCHER (Yellow Box - BOTTOM CENTER)
```
Position: Bottom Center
Box Color: Yellow (#FFE066)

Actions:
→ Monitors dashboard
→ Reviews reports
→ Assigns responders
→ Updates incident status
→ Sends notifications
→ Manages dispatch
```

#### RESPONDER (Yellow Box - RIGHT)
```
Position: Right Side
Box Color: Yellow (#FFE066)

Actions:
→ Receives alert
→ Accepts/rejects dispatch
→ Updates GPS location
→ Changes status
→ Submits completion report
→ Views route
```

---

### 6. LucidChart Creation Steps

1. **Create Main Circle**: Place 0.0 Central System in center
2. **Add External Entities**: D1-D4 around the system
3. **Add User Types**: Citizens, Admin, Staff, Responder
4. **Add Processes**: 1.0-5.0 around central circle
5. **Connect Flows**: Draw arrows with data labels
6. **Color Code**:
   - Red (#FF6B6B): External Entities
   - Green (#51CF66): Processes
   - Yellow (#FFE066): Actor/User Types

---

### 7. FLOW SEQUENCE EXAMPLE

```
INCIDENT CREATION TO RESOLUTION:

Step 1: CITIZEN submits report
  Citizen → 2.0 Incident Reporting → 0.0 Central System

Step 2: SYSTEM validates and stores
  2.0 Incident Reporting → D2 Firestore (stores report)

Step 3: STAFF reviews and assigns
  Staff → 3.0 Incident Management → Responder Assignment

Step 4: NOTIFICATION sent
  3.0 Incident Management → 4.0 Notification Services → D4 Notifications → Responder

Step 5: RESPONDER accepts
  Responder → 3.0 Incident Management (status update)

Step 6: ANALYTICS tracked
  D2 Firestore → 5.0 Analytics & Reporting → Admin Dashboard

Step 7: CHATBOT assists
  Citizen → 4.0 Notification Services → D3 Support Chats → Response
```

---

### 8. Key Data Elements in Flow

```
Report Data:
├── reportID (unique identifier)
├── timestamp (creation time)
├── GPS coordinates (lat, lng)
├── category (tanod, fire, police, etc)
├── status (Pending/Approved/Responding/Responded/Declined)
├── description
├── photos/media
└── responderID (assigned)

User Data:
├── userID
├── name
├── email
├── phone
├── role (citizen/admin/staff/responder)
├── verification_status
└── location

Notification Data:
├── notificationID
├── message content
├── priority level
├── timestamp
├── delivery status
└── recipient list
```

---

## IMPLEMENTATION NOTES FOR LUCIDCHART

1. **Central Hub**: Make 0.0 the largest/most prominent process
2. **Color Consistency**: 
   - Red boxes = Data stores (external entities)
   - Green boxes = Processes
   - Yellow boxes = Actors/Users
3. **Arrow Labels**: Every arrow should have data description
4. **Legend**: Add legend explaining colors and symbols
5. **Zoom Areas**: Create separate detailed views for each process
6. **Real-time Sync**: Show bidirectional arrows for Firestore listeners
7. **External APIs**: 
   - Google Maps (GPS/Geocoding)
   - Google Gemini (Chatbot AI)
   - Firebase Cloud Messaging (FCM)

---

## Additional Context for Your System

**Technologies Used:**
- Frontend: Android (Java) + Web (PHP with Tailwind CSS)
- Backend: Firebase (Firestore, Auth, Storage, Cloud Functions, FCM)
- External APIs: Google Maps, Google Gemini AI

**Key Features:**
1. Real-time GPS tracking of incidents and responders
2. Multi-category incident reporting (police, fire, medical, etc)
3. AI chatbot assistance using Google Gemini
4. Push notifications via FCM
5. User verification system
6. Analytics and reporting dashboard
7. Live dispatch coordination

---

This specification can be directly imported into LucidChart by:
1. Creating boxes with the specified colors
2. Adding process/entity labels
3. Drawing arrows with data labels
4. Grouping related items
5. Adding legend and annotations
