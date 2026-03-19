# CHAPTER 1  
## INTRODUCTION

### 1.1 Background of the Study
In many local government units in the Philippines, emergency response still relies heavily on traditional methods such as manual logging of incidents, walk-in reports, and phone calls that are routed through multiple personnel before action is taken. These practices often lead to delayed responses, incomplete information, and difficulty in monitoring the status of emergencies in real time. As urban areas grow and the volume of incidents increases, it becomes more challenging for authorities to coordinate responders efficiently, ensure proper documentation, and analyze trends for planning and decision-making.

San Carlos City, Pangasinan, is no exception to these challenges. While emergency hotlines and physical offices are available, there remains a gap between citizens who urgently need assistance and the responders who must be deployed quickly and accurately. Factors such as unclear location details, miscommunication, and lack of a centralized platform for reports contribute to slower response times and potential risks to life and property.

With the widespread adoption of smartphones and mobile internet, there is an opportunity to modernize how emergencies are reported and handled at the local level. A unified digital platform that allows citizens to send incident reports, share their location, and receive updates—while enabling the command center to verify, track, and dispatch responders—can significantly improve the overall efficiency of the emergency response process. The **ManResponde System** is designed to address these needs by providing a city-wide, technology-enabled emergency response solution.

---

### 1.2 Purpose and Description of the Study
The primary purpose of this study is to design, develop, and evaluate the **ManResponde Emergency Response System** for San Carlos City. The system aims to serve as a centralized platform that connects citizens, the command center, and field responders in real time, using a combination of web-based and mobile technologies.

The study describes the analysis, design, implementation, and initial evaluation of ManResponde as an emergency coordination platform. The system allows registered users to report incidents through a mobile application or web interface, automatically capture their approximate location, and categorize the type of emergency (e.g., police, fire, medical, flood, and other incidents). At the back end, authorized staff at the command center can view and verify incoming reports, assess their priority, assign appropriate responders, and monitor the status of each incident through a dashboard with mapping and reporting features.

By documenting the development process and assessing the system based on selected criteria (such as usability, reliability, and responsiveness), the study seeks to demonstrate how a localized, technology-driven solution can enhance public safety operations at the city level.

---

### 1.3 Objectives of the Study

#### General Objective
- To develop and evaluate the ManResponde Emergency Response System that will facilitate faster, more organized, and data-driven emergency response operations in San Carlos City.

#### Specific Objectives
1. To analyze the existing emergency reporting and response process of San Carlos City and identify operational gaps, delays, and pain points.  
2. To design a system architecture that integrates citizen reporting, command center verification, and responder dispatch using a centralized dashboard and mapping interface.  
3. To develop a functional prototype of the ManResponde System consisting of:  
   - A citizen-facing mobile/web interface for submitting emergency reports; and  
   - An administrative dashboard for monitoring incidents, verifying reports, and managing responders.  
4. To implement location-based features that allow the system to capture the approximate location of incidents and visualize them on a map for better situational awareness.  
5. To generate summary reports and basic analytics on the volume, type, and location of incidents to support planning and decision-making by local authorities.  
6. To evaluate the system in terms of usability, efficiency, reliability, and user satisfaction among selected citizens, responders, and staff of the command center.

---

### 1.4 Conceptual Framework (Input–Process–Output Model)

The conceptual framework of this study is based on the Input–Process–Output (IPO) model.

#### Input
- Existing emergency response procedures and documentation in San Carlos City.  
- Requirements gathered from key stakeholders such as citizens, responders, and command center staff.  
- Hardware and software resources (e.g., web server, database server, mobile devices, internet connectivity).  
- Design standards and best practices for usability, security, and data privacy.  

#### Process
1. **Requirements Analysis** – Conduct interviews, observations, and document reviews to identify functional and non-functional requirements of the system.  
2. **System Design** – Create the system architecture, database schema, user interface designs, and data flow diagrams for the ManResponde System.  
3. **System Development** – Implement the web and mobile components, including the reporting interface, dashboard, notification mechanism, and mapping module.  
4. **Testing and Validation** – Perform unit testing, integration testing, and user acceptance testing to ensure that the system meets the identified requirements.  
5. **Deployment and Initial Training** – Deploy the system in a test environment and provide initial training to selected users and staff.  
6. **Evaluation** – Collect feedback and assess the system based on predefined criteria such as usability, reliability, responsiveness, and user satisfaction.

#### Output
- A functional ManResponde Emergency Response System that:  
  - Enables citizens to submit emergency reports with location details.  
  - Provides a real-time dashboard for monitoring, verifying, and dispatching incidents.  
  - Generates summary and analytical reports to support decision-making.  
- Improved coordination and documentation in the emergency response process of San Carlos City.

---

### 1.5 Scope and Limitations of the Study

#### Scope
This study focuses on the design, development, and initial evaluation of the ManResponde Emergency Response System for San Carlos City, Pangasinan. The scope includes:

- Development of a web-based administrative dashboard for the command center and authorized personnel.  
- Implementation of a mobile-friendly interface for citizens to submit emergency reports and view basic status updates.  
- Integration of location-based features for mapping incidents and visualizing reports on a city map.  
- Basic reporting and analytics functions, such as viewing the number and type of incidents within a given period.  
- Evaluation of the system with a limited number of respondents, including selected citizens, emergency responders, and command center staff.

#### Limitations
- The system’s performance and availability are dependent on internet connectivity and the reliability of the hosting infrastructure.  
- Location accuracy may vary depending on the user’s device, GPS capability, and network conditions.  
- Full city-wide deployment, long-term operational use, and integration with existing national or regional emergency systems are beyond the initial scope of this study.  
- The security, privacy, and data protection mechanisms implemented are limited to those feasible within the context and resources of the study and may require further enhancement for large-scale production use.  
- The evaluation is limited to selected participants and may not fully represent all potential users of the system.

---

### 1.6 Definition of Terms

For clarity, the following terms are defined as they are used in this study:

- **ManResponde** – The proposed emergency response system developed in this study for San Carlos City, designed to facilitate reporting, monitoring, and coordination of emergency incidents.  
- **Emergency Report** – A digital submission made by a citizen or user through the system describing an incident that requires immediate attention, such as crime, fire, medical emergency, or natural disaster.  
- **Command Center** – The office or unit of the local government responsible for receiving emergency reports, verifying information, and coordinating the response of appropriate agencies.  
- **Responder** – A person or unit (e.g., police, fire, ambulance, barangay tanod) assigned to handle and respond to reported emergencies.  
- **Dashboard** – The web-based interface used by authorized staff to view, verify, and manage incidents, including their status, location, and assigned responders.  
- **Location-Based Services (LBS)** – Features of the system that use geographic location data, such as GPS coordinates, to identify and visualize where an incident occurred.  
- **Incident Status** – The current stage of a reported emergency, such as pending, verified, dispatched, on-going, and resolved.  
- **User** – Any individual who interacts with the ManResponde System, including citizens, command center staff, and field responders, depending on their access level.

---

### 1.7 Review of Related Literature

Several studies and systems have explored the use of information and communication technologies to improve emergency response and public safety. Early implementations of computer-aided dispatch (CAD) systems in developed countries focused on integrating radio communication, telephone calls, and mapping tools to support centralized dispatch centers. These systems demonstrated that digital tools can significantly reduce response times and improve coordination between agencies.

In recent years, mobile and web technologies have enabled citizen-centric emergency reporting applications. Various local and international projects have introduced mobile apps that allow users to send distress signals, share their location, and request assistance with a single tap. These applications emphasize ease of use, real-time communication, and integration with government or private response units. Studies on such systems highlight the importance of user-friendly interfaces, reliable connectivity, and accurate location tracking in ensuring effective adoption and impact.

Within the Philippine context, several local government units have begun adopting ICT-based solutions such as SMS-based hotlines, social media reporting, and basic web portals for disaster and emergency information. However, many of these solutions are fragmented and not fully integrated into a unified platform that supports end-to-end incident management—from citizen report to closure and documentation. Research on local implementations underscores the need for systems that are tailored to the specific context of cities and municipalities, taking into account available infrastructure, institutional capacity, and user behavior.

The development of the ManResponde System aligns with these trends by providing a localized, integrated platform for emergency reporting and response. Unlike generic communication channels, ManResponde focuses on structured data capture, location visualization, status tracking, and reporting functions specifically for San Carlos City. By consolidating features inspired by existing ICT-based emergency systems and adapting them to the needs of the local government, this study contributes to the body of knowledge on how technology can enhance public safety operations at the city level.
