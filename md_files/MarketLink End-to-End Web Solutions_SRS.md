## MarketLink

Farm Fresh Just a Click Away

## Software Requirements Specification Version 1.0

Project Name: MarketLink

Theme: eGreen Basket

Category: End-to-End Web solutions


## Table of Contents


## 1.1 Background and Necessity for the Web Application

Local farmers markets are growing in popularity as shoppers look for fresh, seasonal, and locally grown produce. However, customers rarely know in advance which Farmers will be at a market on a given day, what stock they have, or at what price.

Availability is usually communicated informally through chalkboards, printed flyers, or word of mouth. This means customers often arrive to find popular items already sold out or make a trip only to discover a Farmer is closed for the week.

Farmers, in turn, have no easy way to publicize their weekly inventory, take pre-orders ahead of market day, or build a lasting relationship with regular customers. A full-stack Web application is required to bring Farmers and customers together on a single platform. Through this platform, Farmers can publish their weekly stock, pricing, and manage incoming pre-orders.

Additionally, customers can discover nearby markets, view market and Farmer locations through an integrated map using Google Maps API or OpenStreetMap, browse what is available, reserve items for pickup, and leave feedback. Centralizing this information reduces wasted trips, helps Farmers plan their harvest and stock better, and strengthens the connection between local producers and their community.


## 1.2 Proposed Solution

The proposed full-stack Web application titled ‘MarketLink’ aims to provide a unified platform that connects local farmers-market Farmers with customers. Farmers can register, list their weekly stock and prices, manage pre-orders placed by customers for pickup at the market, and provide accurate stall or pickup locations that can be displayed through Google Maps API or OpenStreetMap.

Some of the actions that customers can perform:

- Browse markets and Farmers near them

- Search and filter available products

- Use Google Maps API or OpenStreetMap to locate nearby markets and Farmers, view directions, and identify pickup points

- Place pre-orders for pickup

- Track their order history

- Save preferent Farmers and products

- Leave reviews and ratings

The platform maintains a database of Farmer inventories, customer order history, and favorites to help customers quickly find items. Along with an optional basic AI feature, they can get answers to common questions such as market timings, Farmer availability, and product details. By combining inventory management, pre-ordering, and optional basic AI intelligent assistance in one system, MarketLink makes shopping at local farmers markets more convenient, predictable, and personal.

## 1.3 Purpose of the Document

This document outlines the design, functional expectations, non-functional criteria, and implementation guidelines for the ‘MarketLink’ Web application. It serves as a reference point for developers, testers, project managers, and stakeholders involved in the creation and evaluation of the system.


## 1.4 Scope of Project

This project involves the development of a comprehensive full-stack Web-based application that connects local farmers-market Farmers with customers. The application allows Farmers to register their stall, list their weekly stock and prices, manage pre-orders, and map their stall or pickup location using Google Maps API or OpenStreetMap.

Customers can create accounts, browse markets and Farmers, search and filter products, view map-based market and Farmer locations, place or cancel pre-orders for pickup, save favorite Farmers/products, and leave reviews and ratings.

The platform maintains a database of Farmer inventories, customer order history, and favorites. It supports secure registration, role-based access, and interactive dashboards for both Farmers and customers. An optional basic AI assistant/chatbot is included that helps customers search for items and provides answers to frequently asked questions.

An administrator role takes care of the elevated management such as managing users, report generation, and so on.

The Web solution is intended to be responsive, intuitive, and scalable, ensuring smooth communication between Farmers and customers, accurate stock visibility, and a convenient pre-order and pickup experience within a single platform.


## Architecture Diagram for MarketLink

MarketLink follows a standard multi-tier Web architecture, as shown in figure.


## Flow Diagram

A sample flow diagram depicting the interaction between the entities and the application is given. Both Farmers and Customers interact with the central MarketLink Web Application, which in turn is backed by the shared database.

## 1.5 Constraints

the *

The development of

constraints to ensure its successful implementation and operation. Technically, the application must be compatible with major Web browsers and should be responsive across various devices. You might encounter constraints related to data storage, data synchronization, and backup procedures. Usage of various images and videos can be subject to licensing agreements and copyright restrictions. It is important to understand and comply with these constraints to avoid legal issues.

the ‘MarketLink’ Web application must adhere to several


The Web application will not have any functionality for payment gateways – payment for pre-orders is settled in person at pickup. Delivery/courier logistics are out of scope; the application only supports pickup at the market. Verification of Farmer’s identity, licensing, or organic/food-safety certification will not be a part of the Web application functionality.

## 1.6 Functional Requirements

Application will be designed with a set of forms/pages with menus representing the choice of activities to be performed.

## Customer Features

## User Registration and Login

- Customers must be able to register, log in, and securely access their dashboard.

- Name, contact number, e-mail ID, and address must be supplied during registration.

- Customers can save multiple favorite Farmers and products.

- Sharing of accounts among family members can be permitted (optional).

## Browse Markets and Farmers

- Customers can browse nearby farmers markets by location and day and view the list of Farmers present at each market.

- Customers can view a Farmers profile including stall name, location, operating days, and current weekly stock.

- Customers can view markets and Farmer stalls on an embedded map powered by Google Maps API or OpenStreetMap, with location markers and directions to the selected pickup point.

## Search, Browse, and Filter Products

- Customers can browse product categories (vegetables, fruits, dairy, baked goods, and so on) with filters for price, category, market, and day.

- Customers can view product details including price, unit, quantity available, and Farmer.


## Place Pre-Orders for Pickup

- Customers can add products to a cart and place a pre-order against the Farmer’s available stock.

- Customers can select a pickup date and time slot within the Farmer-s available windows.

- Customers can view order status (placed, accepted, ready for pickup, completed) and cancel or modify an order before the Farmer’s cutoff time. Payment functionality will not be included; orders are paid for at pickup.

## Manage Orders

- Customers should be able to do the following actions:

- o View Orders

- o Modify Orders

- o Cancel Orders

## Order History and Favorites

- Customers can view their past orders and quickly reorder previously purchased items.

- Customers can mark Farmers and products as favorites for quick access and restock alerts.

- Customers can save preferred market locations and receive route-friendly pickup details through Google Maps API or OpenStreetMap integration.

## AI Assistant (Optional)

- An AI-powered chatbot should help customers find specific items across markets and Farmers.

- The assistant should answer common questions such as market timings, Farmer availability, pickup windows, and product details.

## Reviews and Ratings

- Customers can rate and review Farmers and individual products after an order is completed.

- Customers can view reviews left by other customers before ordering.


## Farmer Features

## Farmer Registration and Profile Management

- At the time of registration, Farmers must provide stall/business name, contact person, contact number, e-mail ID, and address.

- Once logged in, Farmers can enhance their profile including the markets they sell at, operating days, pickup time windows, and location details such as address, map pin, latitude, and longitude for display through Google Maps API or OpenStreetMap.

## Manage Weekly Stock and Pricing

- Farmers can add, edit, view, or delete products including name, category, price, unit, quantity available, description, and image.

- Farmers can set up a recurring weekly stock template and adjust it as availability changes.

- Farmers can mark items as sold out or temporarily unavailable.

## Manage pre-orders

- Farmers can view incoming pre-orders, accept or decline them, and mark orders as ready for pickup.

- Farmers can set order cut-off times and manage available pickup slots.

## View Order History and Insights

- Farmers can view past sales, order history, and their best-selling products. Also, Farmers should be able to see Total Orders, Pending Orders, Revenue Summary.

## Respond to Reviews

- Farmers can view and optionally respond to customer reviews left on their products.

## Admin Features

## Admin Login and Dashboard

- Admin can securely log in to a dedicated dashboard separate from the Customer and Farmer views.


- The dashboard should summarize key platform metrics such as total Farmers, total customers, total markets, and total orders.

## Manage Farmers and Customers

- Admin can view, approve, or suspend Farmer registrations before they can list products.

- Admin can view, activate, or deactivate customer accounts in case of policy violations.

## Manage Markets

- Admin can add, edit, or remove farmers markets, including name, address, operating days, timings, and map coordinates or embedded map links using Google Maps API or OpenStreetMap.

## Content Moderation

- Admin can view and remove inappropriate product listings or customer reviews that violate platform guidelines.

## Reports and Analytics

- Admin can view platform-wide reports covering total orders, revenue summary across markets, and the most active Farmers.

## System Configuration

- Admin can manage master data such as product categories and publish platform-wide notifications or announcements.

## Other Features

## Role-Based Access Control

- Users can access only those features relevant to their role (Farmer or Customer).

## Search, Sort, Filter

- Customers should be able to search markets, Farmers, or products based on filters like location, category, price, and market day, with map-based results using Google Maps API or OpenStreetMap wherever location discovery is required.


## Responsive Design

- The application will be mobile-friendly and accessible across various devices.

## Notifications

- Customers should receive e-mail or in-app alerts for order confirmations and orders ready for pickup.

## Feedback and Ratings

- Users can rate Farmers or products and leave comments.

## About Us

- Information about the team and the platform should be displayed.

## Contact Us

- Display static team contact information with Google Maps showing location.


## Important Note Regarding AI Usage:

You are encouraged to use AI-powered tools (such as AI-assisted Website builders, UI/UX design tools, code assistants, and image-generation tools) to enhance productivity and creativity. However, AI should be used as a supporting aid rather than a substitute for your own design, development, and problem-solving skills.

Do NOT rely on completely ready-made Website templates for your project, as this will adversely affect your evaluation. Your Web application’s design, structure, and implementation should primarily reflect your own skills and understanding. Do NOT submit AI-generated code or content without meaningful modification and understanding. AI-generated suggestions may be used for guidance, learning, debugging, or improving productivity, but the final solution should demonstrate your own effort, logic, and implementation.

Acknowledge all the AI tool(s) used (for example, Copilot, Canva AI, Figma AI, Uizard, or similar) in your project documentation or submission.

During evaluation, judges may ask participants to explain their design decisions, implementation approach, and code. You are therefore expected to understand and be able to justify all aspects of your submitted work.

Do not use AI tools to fully produce ready-made documentation. This is strictly forbidden.

Bottomline: AI is your assistant, not your developer. Your knowledge, creativity, and coding skills should drive the project.


## 1.7 Non-Functional Requirements

There are several non-functional requirements that should be fulfilled by the application. They include:

- Safe to use: The application should not result in any malicious downloads or unnecessary file downloads.

- Accessibility: The application should have clear and legible fonts, user-interface elements, and navigation elements.

- User-friendliness: The application should be easy to navigate with clear menus and other elements and easy to understand.

- Operability: The application should be reliable and efficient.

- Performance: The application should demonstrate high value of performance through speed and throughput. In simple terms, the application should have minimal load time and smooth page redirection, even when browsing large product catalogues.

- Scalability: The application architecture and infrastructure should be designed to handle increasing numbers of Farmers, customers, and product listings, especially during peak market days.

- Security: The application should implement adequate security measures such as authentication. For example, only registered users can access certain features.

- Availability: The application should be available 24/7 with minimum downtime, so customers can browse and pre-order at any time before pickup.

- Compatibility: The application should be compatible with latest browsers and various devices.

## IMPORTANT:

These are the bare minimum expectations from the project. It is a must to implement the FUNCTIONAL and NON-FUNCTIONAL requirements given in this SRS. Once they are complete, you can use your own creativity and imagination to add more features if required.


## 1.8 Interface Requirements

## Hardware

- Intel Core i5/i7 Processor or higher

- 8 GB RAM or higher

- Color SVGA monitor

- 500 GB Hard Disk space

- Mouse

- Keyboard

## Software

## IDE:

Appropriate IDE as per the platform

## Frontend:

HTML5, CSS3, Bootstrap, ReactJS/AngularJS/Angular/TypeScript, JavaScript, jQuery, and XML

## Backend:

- Java SDK with Apache NetBeans or Eclipse, Jakarta EE

- OR C# with ASP.NET MVC and ASP.NET MVC Core (optional), Visual Studio IDE

- OR PHP with Laravel Framework

- OR Python with Flask or Django

- OR MongoDB, Express.js, Angular, Node.js

- OR MongoDB, Express.js, React, Node.js

## Database:

MySQL/SQL Server/MongoDB/JSON

## For local hosting (optional):

XAMPP latest version

## Maps and Geolocation:

- Google Maps API or OpenStreetMap for locating markets and Farmers, displaying map markers, showing directions, and storing latitude/longitude values for accurate pickup points.

## AI Tools:

- Optional AI assistant/chatbot may be implemented using tools such as tawk.to or Zapier.


## Database Design

Based on the specifications given, you can define suitable entities, attributes for these entities, and identify relationships between the entities. For example, some entities along with their attributes can be identified as follows:

## Users

| Attribute | Data Type | Key |
| --- | --- | --- |
| user_id | INT | PK |
| username | VARCHAR(50) | UNIQUE |
| password_hash | VARCHAR(255) |   |
| email | VARCHAR(100) | UNIQUE |
| role | VARCHAR(20) |   |
| created_at | DATETIME |   |

## Products

| Attribute | Data Type | Key |
| --- | --- | --- |
| product_id | INT | PK |
| Farmer_id | INT | FK |
| name | VARCHAR(100) |   |
| description | TEXT |   |
| price | DECIMAL(10,2) |   |
| stock_quantity | INT |   |
| created_at | DATETIME |   |

## Orders

| Attribute | Data Type | Key |
| --- | --- | --- |
| order_id | INT | PK |
| customer_id | INT | FK |
| product_id | INT | FK |
| quantity | INT |   |
| total_amount | DECIMAL(10,2) |   |
| order_status | VARCHAR(20) |   |
| order_date | DATETIME |   |

## Reviews

| Attribute | Data Type | Key |
| --- | --- | --- |
| review_id | INT | PK |
| product_id | INT | FK |
| customer_id | INT | FK |


| rating | INT |   |
| --- | --- | --- |
| comment | TEXT |   |
| review_date | DATETIME |   |

## Reports

| Attribute | Data Type | Key |
| --- | --- | --- |
| report_id | INT | PK |
| generated_by | INT | FK |
| report_type | VARCHAR(50) |   |
| generated_at | DATETIME |   |

## Markets

| Attribute | Data Type | Key |
| --- | --- | --- |
| market_id | INT | PK |
| market_name | VARCHAR(100) |   |
| address | TEXT |   |
| latitude | DECIMAL(10,8) |   |
| longitude | DECIMAL(11,8) |   |
| map_provider | VARCHAR(30) |   |

Note: These are just examples, you may design your own table structure as per your logic.


## 1.9 Project Deliverables

You are expected to design and build the project and submit it along with a complete project report that includes:

- Problem Definition

- Design Specifications

- Diagrams such as Flowcharts for various Activities, Data Flow Diagrams, and so on

- Database Design

- Test Data Used in the Project

- Project Installation Instructions (MANDATORY)

- User Credentials for all Types of Users with Passwords (MANDATORY)

Documentation is considered a very important part of the project. Ensure that documentation is complete and comprehensive.

Documentation should not contain any source code.

The consolidated project will be submitted as a zip file with a ReadMe.doc file listing assumptions (if any) made at your end and SQL scripts files (.sql) containing database and table definitions.

Note: Preferably, host the working Web application on a Website and share the URL for evaluation.

Submit a video (.mp4 file) demonstrating the working of the Web application, including all features under Functional Requirements. This is MANDATORY.

Over and above the given specifications, you can apply your creativity and logic to improve the system.

~~~ End of Document ~~~
