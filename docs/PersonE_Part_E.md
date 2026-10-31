Part E: Testing, Endpoint Documentation and Deployment
Person 4. Contents: 1. API endpoint documentation, 2. Sample responses and status codes, 3. Deployment guide. The Postman collection is submitted as a separate .json file.
1. API endpoint documentation
Version 1 of the CampusEvents API. It lets students browse and register for campus events, and lets organizers create and manage their own events.
    • Base URL (local): http://127.0.0.1:8000/api/v1
    • Format: JSON in and out
    • Authentication: Bearer token (Laravel Sanctum)
    • Postman: import postman/CampusEvents.postman_collection.json and run the folders in order
    • Sample responses and status codes: docs/SAMPLE_RESPONSES.md
Every request should send these headers:
Accept: application/json
Content-Type: application/json        (for requests with a body)
Authorization: Bearer <token>         (for every endpoint except register and login)
Send Accept: application/json on every request. Without it, validation errors come back as redirects or HTML instead of JSON.

Roles
Every user has one role. The role decides what they can do.
Role	How it is assigned	Can do
student	Given automatically on self-registration	Browse events, view details, register for events
organizer	Set in the database or by the seeder (there is no endpoint for it)	Everything a student can do, plus create, update and cancel their own events
admin	Set in the database (there is no endpoint for it)	Everything an organizer can do, plus update and cancel any event
A student who tries an organizer action gets 403 Forbidden. An organizer who tries to edit someone else’s event also gets 403 Forbidden.

Authentication
Register
POST /register (no token needed)
Creates a student account and returns a token.
{
  "name": "Ada Student",
  "email": "ada@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
Rules: name (required, max 255), email (required, valid, unique), password (required, min 8, must match password_confirmation).
201 Created
{
  "user": { "id": 3, "name": "Ada Student", "email": "ada@example.com", "role_id": 1, "role": { "id": 1, "name": "student" } },
  "token": "1|abcdef..."
}
Login
POST /login (no token needed)
{ "email": "ada@example.com", "password": "password123" }
200 OK returns the same shape as register (user and token).
401 Unauthorized if the email or password is wrong:
{ "message": "Invalid email or password." }
Current user
GET /user (token needed)
Returns the logged-in user, with their role loaded.
Logout
POST /logout (token needed)
Deletes only the token used for this request. Other tokens for the same user keep working.
200 OK
{ "message": "Logged out." }

Categories
List categories
GET /categories (token needed)
Returns every category, sorted by name. Not paginated.
[
  { "id": 1, "name": "Academic", "slug": "academic" },
  { "id": 2, "name": "Career", "slug": "career" }
]
Seeded categories: Academic, Sports, Career, Conference, Workshop, Club Activities, Community Outreach, Entertainment.

Events
Event object
Field	Type	Notes
id	integer	
title	string	max 255
description	string	
category_id	integer	must exist in categories
organizer_id	integer	the user who created the event
venue	string	max 255
event_date	date YYYY-MM-DD	
start_time	time HH:MM:SS	
end_time	time HH:MM:SS	always after start_time
max_capacity	integer	at least 1
banner_path	string or null	stored but not used by the web pages yet
registration_deadline	datetime YYYY-MM-DD HH:MM:SS	must be on or before the event’s start
status	string	upcoming, ongoing, completed or cancelled
created_at, updated_at	datetime	
category	object	{ id, name, slug }, included on list, show and create
organizer	object	{ id, name }, included on list and show
Only upcoming events are open for registration.
List events
GET /events (token needed)
Returns upcoming events (status upcoming, date today or later), soonest first, 15 per page.
Query parameters:
Parameter	Description
page	Page number, starting at 1
mine=1	Organizers only: returns their own events in every status, including cancelled and past ones. Used by the Manage Events page.
200 OK (paginated)
{
  "current_page": 1,
  "data": [ { "id": 4, "title": "Campus Innovation Day", "...": "..." } ],
  "first_page_url": "http://127.0.0.1:8000/api/v1/events?page=1",
  "from": 1,
  "last_page": 1,
  "last_page_url": "http://127.0.0.1:8000/api/v1/events?page=1",
  "links": [],
  "next_page_url": null,
  "path": "http://127.0.0.1:8000/api/v1/events",
  "per_page": 15,
  "prev_page_url": null,
  "to": 6,
  "total": 6
}
Read data for the events and last_page to know how many pages there are.
Show event
GET /events/{id} (token needed)
200 OK returns one event object, including category and organizer.
404 Not Found if the event doesn’t exist:
{ "message": "No query results for model [App\\Models\\Event] 999999" }
Cancelled events still return 200, with "status": "cancelled".
Create event
POST /events (organizer or admin)
{
  "title": "Postman Test Event",
  "description": "Created through the API.",
  "category_id": 1,
  "venue": "Hall 1",
  "event_date": "2026-11-20",
  "start_time": "10:00",
  "end_time": "12:00",
  "max_capacity": 25,
  "registration_deadline": "2026-11-20 09:00:00",
  "banner_path": null
}
Field	Required	Rule
title	yes	string, max 255
description	yes	string
category_id	yes	integer, must exist
venue	yes	string, max 255
event_date	yes	date, today or later
start_time	yes	HH:MM
end_time	yes	HH:MM, after start_time
max_capacity	yes	integer, at least 1
registration_deadline	yes	date or datetime, on or before the event’s start
banner_path	no	string, max 255, or null
The API sets organizer_id to the logged-in user and status to upcoming.
201 Created returns the new event.
422 Unprocessable Content for validation errors. The body lists each problem:
{
  "message": "The registration deadline must be on or before the event start time.",
  "errors": {
    "registration_deadline": ["The registration deadline must be on or before the event start time."]
  }
}
Update event
PUT /events/{id} or PATCH /events/{id} (organizer or admin; organizers can only update their own events)
Send only the fields you want to change. The same rules as create apply to the fields you send. The end-after-start and deadline-before-start checks use the saved values for any field you don’t send.
{ "title": "Campus Innovation Day (updated)", "end_time": "13:00" }
200 OK returns the updated event.
403 Forbidden if the user isn’t an organizer or admin, or if an organizer updates someone else’s event.
422 for validation errors, including an end time that isn’t after the start time.
Cancel event
DELETE /events/{id} (organizer or admin; same ownership rule as update)
Events are cancelled, not deleted, so registrations stay linked.
200 OK
{ "message": "Event cancelled." }
Register for event
POST /events/{id}/register (student only)
No request body.
201 Created
{
  "message": "Registration successful.",
  "registration": { "id": 1, "event_id": 4, "user_id": 3, "event": { "...": "..." } }
}
Status	When	Message
403	The user is not a student	(role error)
409	The student is already registered	You are already registered for this event.
422	The event is not upcoming	This event is not open for registration.
422	The registration deadline has passed	The registration deadline has passed.
422	The event is full	This event is full.
A full event is checked inside a database lock, so two students can’t take the last seat at the same moment.

Errors
All errors are JSON when the request includes Accept: application/json.
Status	Meaning	Example body
401	No token, or an invalid or revoked token	{ "message": "Unauthenticated." }
403	Logged in, but the role or ownership doesn’t allow this	{ "message": "You do not have permission to perform this action." }
404	The event doesn’t exist	{ "message": "No query results for model [App\\Models\\Event] 999999" }
409	Conflict, such as registering twice	{ "message": "You are already registered for this event." }
422	Validation failed, or a business rule blocked the action	{ "message": "...", "errors": { "field": ["..."] } }
500	Something went wrong on the server	Check storage/logs/laravel.log
An organizer who edits someone else’s event gets { "message": "You can only manage your own events." }.

Example: a student registering for an event
# 1. Log in
curl -X POST http://127.0.0.1:8000/api/v1/login \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"student@example.com","password":"password"}'

# 2. Browse events (use the token from step 1)
curl http://127.0.0.1:8000/api/v1/events \
  -H "Accept: application/json" -H "Authorization: Bearer YOUR_TOKEN"

# 3. Register for event 4
curl -X POST http://127.0.0.1:8000/api/v1/events/4/register \
  -H "Accept: application/json" -H "Authorization: Bearer YOUR_TOKEN"
On Windows Command Prompt, use the Postman collection instead of these commands, because quoting JSON in cmd is awkward.

Seeded test accounts
Created by php artisan db:seed (or migrate --seed). Development only. See docs/DEPLOYMENT.md before using them anywhere else.
Email	Password	Role
test@example.com	password	organizer (owns 10 sample events)
student@example.com	password	student
2. Sample responses and status codes
These responses were captured from a running copy of the API (APP_DEBUG=false) and pasted in unchanged, apart from long event lists being cut to one item. Dates, IDs, tokens and timestamps will differ on your server.
Each example uses the same request order as the Postman collection, so the event IDs and tokens match the flow in API.md.
Status code summary
Method	Endpoint	Status codes returned
GET	/api/v1/categories	200
GET	/api/v1/events	200, 401
POST	/api/v1/events	201, 403, 422
DELETE	/api/v1/events/{id}	200
GET	/api/v1/events/{id}	200, 404
PUT	/api/v1/events/{id}	200, 422
POST	/api/v1/events/{id}/register	201, 403, 409, 422
POST	/api/v1/login	200, 401
POST	/api/v1/logout	200
POST	/api/v1/register	201
GET	/api/v1/user	200
Status codes used across the API:
Code	Meaning in this API
200 OK	Read, update, cancel or logout succeeded
201 Created	Account registered, event created, or registration saved
401 Unauthorized	No token, invalid token, or wrong login details
403 Forbidden	Logged in, but the role or ownership doesn’t allow the action
404 Not Found	No event with that ID
409 Conflict	Already registered for the event
422 Unprocessable Content	Validation failed, or a business rule blocked the action (full, closed, deadline passed)
Authentication
Register a student account
POST /api/v1/register → 201
Returns the new user and a token.
{
  "user": {
    "name": "Sample Student",
    "email": "sample152918@example.com",
    "role_id": 1,
    "updated_at": "2026-10-21T13:29:19.000000Z",
    "created_at": "2026-10-21T13:29:19.000000Z",
    "id": 3,
    "role": {
      "id": 1,
      "name": "student",
      "created_at": "2026-10-21T13:29:18.000000Z",
      "updated_at": "2026-10-21T13:29:18.000000Z"
    }
  },
  "token": "1|8MXOWFWdY8BVLMKk49vVKMCWFA71z1i80ydyLoyG4772c4f2"
}
Login as organizer
POST /api/v1/login → 200
{
  "user": {
    "id": 1,
    "name": "Test User",
    "email": "test@example.com",
    "email_verified_at": null,
    "created_at": "2026-10-21T13:29:18.000000Z",
    "updated_at": "2026-10-21T13:29:18.000000Z",
    "role_id": 2,
    "role": {
      "id": 2,
      "name": "organizer",
      "created_at": "2026-10-21T13:29:18.000000Z",
      "updated_at": "2026-10-21T13:29:18.000000Z"
    }
  },
  "token": "2|FekcSY3l75Fiddsh1I0Vt0jwQGeWbv4ks1fafYEz9c85a2c5"
}
Login as student
POST /api/v1/login → 200
{
  "user": {
    "id": 2,
    "name": "Test Student",
    "email": "student@example.com",
    "email_verified_at": null,
    "created_at": "2026-10-21T13:29:18.000000Z",
    "updated_at": "2026-10-21T13:29:18.000000Z",
    "role_id": 1,
    "role": {
      "id": 1,
      "name": "student",
      "created_at": "2026-10-21T13:29:18.000000Z",
      "updated_at": "2026-10-21T13:29:18.000000Z"
    }
  },
  "token": "3|rlH45J821a8fhSu1Ok53HAZlyhg6XR9GnYj0meVme59da10a"
}
Login with wrong password
POST /api/v1/login → 401
{
  "message": "Invalid email or password."
}
Current user
GET /api/v1/user → 200
{
  "id": 1,
  "name": "Test User",
  "email": "test@example.com",
  "email_verified_at": null,
  "created_at": "2026-10-21T13:29:18.000000Z",
  "updated_at": "2026-10-21T13:29:18.000000Z",
  "role_id": 2,
  "role": {
    "id": 2,
    "name": "organizer",
    "created_at": "2026-10-21T13:29:18.000000Z",
    "updated_at": "2026-10-21T13:29:18.000000Z"
  }
}
Categories
List categories
GET /api/v1/categories → 200
[
  {
    "id": 1,
    "name": "Academic",
    "slug": "academic"
  },
  {
    "id": 3,
    "name": "Career",
    "slug": "career"
  },
  {
    "id": 6,
    "name": "Club Activities",
    "slug": "club-activities"
  },
  {
    "id": 7,
    "name": "Community Outreach",
    "slug": "community-outreach"
  },
  {
    "id": 4,
    "name": "Conference",
    "slug": "conference"
  },
  {
    "id": 8,
    "name": "Entertainment",
    "slug": "entertainment"
  },
  {
    "id": 2,
    "name": "Sports",
    "slug": "sports"
  },
  {
    "id": 5,
    "name": "Workshop",
    "slug": "workshop"
  }
]
Events
List upcoming events (page 1)
GET /api/v1/events → 200
{
  "current_page": 1,
  "data": [
    {
      "id": 5,
      "title": "Accusantium commodi nam impedit eligendi.",
      "description": "Quia est id rem ipsum neque. Voluptatibus voluptatibus maiores error eos quo cumque. Eos reprehenderit id doloribus ducimus necessitatibus odio error doloremque. Magni dolore perspiciatis et quia.",
      "category_id": 5,
      "organizer_id": 1,
      "venue": "48375 Kertzmann Ridge Suite 947",
      "event_date": "2026-11-04",
      "start_time": "13:00:00",
      "end_time": "15:00:00",
      "max_capacity": 162,
      "banner_path": null,
      "registration_deadline": "2026-10-22 10:17:30",
      "status": "upcoming",
      "created_at": "2026-10-21T13:29:18.000000Z",
      "updated_at": "2026-10-21T13:29:18.000000Z",
      "category": {
        "id": 5,
        "name": "Workshop",
        "slug": "workshop",
        "created_at": "2026-10-21T13:29:18.000000Z",
        "updated_at": "2026-10-21T13:29:18.000000Z"
      },
      "organizer": {
        "id": 1,
        "name": "Test User"
      }
    }
  ],
  "first_page_url": "http://127.0.0.1:8001/api/v1/events?page=1",
  "from": 1,
  "last_page": 1,
  "last_page_url": "http://127.0.0.1:8001/api/v1/events?page=1",
  "links": [
    {
      "url": null,
      "label": "&laquo; Previous",
      "page": null,
      "active": false
    },
    {
      "url": "http://127.0.0.1:8001/api/v1/events?page=1",
      "label": "1",
      "page": 1,
      "active": true
    },
    {
      "url": null,
      "label": "Next &raquo;",
      "page": null,
      "active": false
    }
  ],
  "next_page_url": null,
  "path": "http://127.0.0.1:8001/api/v1/events",
  "per_page": 15,
  "prev_page_url": null,
  "to": 6,
  "total": 6,
  "_note": "Only the first of 6 events on this page is shown."
}
List my events as organizer (all statuses)
GET /api/v1/events?mine=1 → 200
{
  "current_page": 1,
  "data": [
    {
      "id": 6,
      "title": "Corrupti totam accusantium.",
      "description": "Velit consequuntur ratione excepturi. Eligendi repellendus distinctio provident. Sit voluptatem est nostrum eum. Voluptas itaque ad dolor doloribus sapiente facere.",
      "category_id": 5,
      "organizer_id": 1,
      "venue": "389 Shanahan Underpass",
      "event_date": "2026-07-31",
      "start_time": "09:00:00",
      "end_time": "11:00:00",
      "max_capacity": 385,
      "banner_path": null,
      "registration_deadline": "2026-07-29 04:58:18",
      "status": "cancelled",
      "created_at": "2026-10-21T13:29:18.000000Z",
      "updated_at": "2026-10-21T13:29:18.000000Z",
      "category": {
        "id": 5,
        "name": "Workshop",
        "slug": "workshop",
        "created_at": "2026-10-21T13:29:18.000000Z",
        "updated_at": "2026-10-21T13:29:18.000000Z"
      },
      "organizer": {
        "id": 1,
        "name": "Test User"
      }
    }
  ],
  "first_page_url": "http://127.0.0.1:8001/api/v1/events?page=1",
  "from": 1,
  "last_page": 1,
  "last_page_url": "http://127.0.0.1:8001/api/v1/events?page=1",
  "links": [
    {
      "url": null,
      "label": "&laquo; Previous",
      "page": null,
      "active": false
    },
    {
      "url": "http://127.0.0.1:8001/api/v1/events?page=1",
      "label": "1",
      "page": 1,
      "active": true
    },
    {
      "url": null,
      "label": "Next &raquo;",
      "page": null,
      "active": false
    }
  ],
  "next_page_url": null,
  "path": "http://127.0.0.1:8001/api/v1/events",
  "per_page": 15,
  "prev_page_url": null,
  "to": 10,
  "total": 10,
  "_note": "Only the first of 10 events on this page is shown."
}
Create event
POST /api/v1/events → 201
{
  "title": "Sample Event",
  "description": "Created for the sample responses.",
  "category_id": 1,
  "venue": "Hall 1",
  "event_date": "2026-11-20",
  "start_time": "10:00",
  "end_time": "12:00",
  "max_capacity": 25,
  "registration_deadline": "2026-11-20 09:00:00",
  "organizer_id": 1,
  "status": "upcoming",
  "updated_at": "2026-10-21T13:29:19.000000Z",
  "created_at": "2026-10-21T13:29:19.000000Z",
  "id": 11,
  "category": {
    "id": 1,
    "name": "Academic",
    "slug": "academic",
    "created_at": "2026-10-21T13:29:18.000000Z",
    "updated_at": "2026-10-21T13:29:18.000000Z"
  }
}
Create event with deadline after start
POST /api/v1/events → 422
Validation error.
{
  "message": "The registration deadline must be on or before the event start time.",
  "errors": {
    "registration_deadline": [
      "The registration deadline must be on or before the event start time."
    ]
  }
}
Create event with empty body
POST /api/v1/events → 422
Every required field is reported.
{
  "message": "The title field is required. (and 8 more errors)",
  "errors": {
    "title": [
      "The title field is required."
    ],
    "description": [
      "The description field is required."
    ],
    "category_id": [
      "The category id field is required."
    ],
    "venue": [
      "The venue field is required."
    ],
    "event_date": [
      "The event date field is required."
    ],
    "start_time": [
      "The start time field is required."
    ],
    "end_time": [
      "The end time field is required."
    ],
    "max_capacity": [
      "The max capacity field is required."
    ],
    "registration_deadline": [
      "The registration deadline field is required."
    ]
  }
}
Show event
GET /api/v1/events/11 → 200
{
  "id": 11,
  "title": "Sample Event",
  "description": "Created for the sample responses.",
  "category_id": 1,
  "organizer_id": 1,
  "venue": "Hall 1",
  "event_date": "2026-11-20",
  "start_time": "10:00",
  "end_time": "12:00",
  "max_capacity": 25,
  "banner_path": null,
  "registration_deadline": "2026-11-20 09:00:00",
  "status": "upcoming",
  "created_at": "2026-10-21T13:29:19.000000Z",
  "updated_at": "2026-10-21T13:29:19.000000Z",
  "category": {
    "id": 1,
    "name": "Academic",
    "slug": "academic",
    "created_at": "2026-10-21T13:29:18.000000Z",
    "updated_at": "2026-10-21T13:29:18.000000Z"
  },
  "organizer": {
    "id": 1,
    "name": "Test User"
  }
}
Update event
PUT /api/v1/events/11 → 200
Only the fields sent are changed.
{
  "id": 11,
  "title": "Sample Event (updated)",
  "description": "Created for the sample responses.",
  "category_id": 1,
  "organizer_id": 1,
  "venue": "Hall 1",
  "event_date": "2026-11-20",
  "start_time": "10:00",
  "end_time": "13:00",
  "max_capacity": 25,
  "banner_path": null,
  "registration_deadline": "2026-11-20 09:00:00",
  "status": "upcoming",
  "created_at": "2026-10-21T13:29:19.000000Z",
  "updated_at": "2026-10-21T13:29:20.000000Z",
  "category": {
    "id": 1,
    "name": "Academic",
    "slug": "academic",
    "created_at": "2026-10-21T13:29:18.000000Z",
    "updated_at": "2026-10-21T13:29:18.000000Z"
  }
}
Update event with end before start
PUT /api/v1/events/11 → 422
{
  "message": "The end time must be after the start time.",
  "errors": {
    "end_time": [
      "The end time must be after the start time."
    ]
  }
}
Registrations
Register for event
POST /api/v1/events/11/register → 201
{
  "message": "Registration successful.",
  "registration": {
    "event_id": 11,
    "user_id": 2,
    "updated_at": "2026-10-21T13:29:20.000000Z",
    "created_at": "2026-10-21T13:29:20.000000Z",
    "id": 1,
    "event": {
      "id": 11,
      "title": "Sample Event (updated)",
      "description": "Created for the sample responses.",
      "category_id": 1,
      "organizer_id": 1,
      "venue": "Hall 1",
      "event_date": "2026-11-20",
      "start_time": "10:00",
      "end_time": "13:00",
      "max_capacity": 25,
      "banner_path": null,
      "registration_deadline": "2026-11-20 09:00:00",
      "status": "upcoming",
      "created_at": "2026-10-21T13:29:19.000000Z",
      "updated_at": "2026-10-21T13:29:20.000000Z"
    }
  }
}
Register again
POST /api/v1/events/11/register → 409
{
  "message": "You are already registered for this event."
}
Register as organizer
POST /api/v1/events/11/register → 403
{
  "message": "You do not have permission to perform this action."
}
Events
Cancel event
DELETE /api/v1/events/11 → 200
{
  "message": "Event cancelled."
}
Registrations
Register for cancelled event
POST /api/v1/events/11/register → 422
{
  "message": "This event is not open for registration."
}
Authentication
Logout
POST /api/v1/logout → 200
Deletes only the token used for this request.
{
  "message": "Logged out."
}
Errors
Request without a token
GET /api/v1/events → 401
{
  "message": "Unauthenticated."
}
Student tries to create an event
POST /api/v1/events → 403
{
  "message": "You do not have permission to perform this action."
}
Event does not exist
GET /api/v1/events/999999 → 404
{
  "message": "No query results for model [App\\Models\\Event] 999999"
}
3. Deployment guide
This guide covers putting CampusEvents on a live server. For running it on your own computer, see the README.

1. Requirements
Software	Version	Notes
PHP	8.3 or newer	Extensions: pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, fileinfo, curl
MySQL or MariaDB	MySQL 8.0 or MariaDB 10.4+	Tested on MariaDB 10.4
Composer	2.x	Only needed if you run composer install on the server
Web server	Apache 2.4 or Nginx	Document root must be the public/ folder
HTTPS certificate	required	API requests carry bearer tokens; never serve them over plain HTTP
Node.js is not needed on the server. The built front-end assets are already in public/build.

2. Get the code onto the server
Copy the project to the server with Git or FTP. Do not copy your development .env file. It contains your local database password and debug settings. Create a fresh .env on the server instead (step 4).
Do not upload the .git folder to a public web directory either.

3. Install dependencies
From the project folder on the server:
composer install --no-dev --optimize-autoloader
The vendor/ folder is already in the project zip, so this step is optional, but it’s the safest way to get packages that match the server’s PHP version.

4. Create the production .env
cp .env.example .env
php artisan key:generate
Edit .env and set at least these values:
APP_NAME=CampusEvents
APP_ENV=production
APP_DEBUG=false
APP_URL=https://events.yourcampus.edu

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=campus_events
DB_USERNAME=campus_events_app
DB_PASSWORD=a-long-random-password

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
Setting	Why
APP_DEBUG=false	Hides stack traces and secrets from users. Must be false in production.
APP_ENV=production	Turns on production behaviour in Laravel.
APP_URL	Must match the public address, including https://.
DB_USERNAME	Use a dedicated database user, not root.

5. Create the database
In MySQL or MariaDB:
CREATE DATABASE campus_events CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'campus_events_app'@'localhost' IDENTIFIED BY 'a-long-random-password';
GRANT ALL PRIVILEGES ON campus_events.* TO 'campus_events_app'@'localhost';
FLUSH PRIVILEGES;
The application user only needs access to its own database.

6. Build the tables
php artisan migrate --force
--force is required when APP_ENV=production, otherwise Laravel refuses to run.

7. Load reference data (not the test accounts)
Run only the reference seeders:
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=CategorySeeder
Run each command once. RoleSeeder fails if the roles already exist.
Do not run php artisan db:seed or migrate --seed on a live server. DatabaseSeeder creates test@example.com and student@example.com with the password password. Anyone can log in as them.
Create the first organizer or admin
The API has no endpoint for assigning roles, so create the first staff account in the database:
php artisan tinker
Then type (one line at a time):
$role = App\Models\Role::where('name', 'organizer')->first();
App\Models\User::create(['name' => 'Events Office', 'email' => 'events@yourcampus.edu', 'password' => 'choose-a-strong-password', 'role_id' => $role->id]);
exit
Use 'admin' instead of 'organizer' for an admin account. Passwords are hashed automatically.

8. File permissions
The web server user (for example www-data on Ubuntu, or the Apache user on Windows) must be able to write to these folders:
chmod -R ug+rw storage bootstrap/cache
Nothing else needs write access.

9. Point the web server at public/
The document root must be public/, so that .env, app/, vendor/ and the other folders can’t be reached from the web.
Apache
Set DocumentRoot /path/to/campus-events-api-WEB303/public and allow the included .htaccess file to run:
<Directory /path/to/campus-events-api-WEB303/public>
    AllowOverride All
    Require all granted
</Directory>
Enable mod_rewrite (a2enmod rewrite).
Nginx
server {
    listen 443 ssl;
    server_name events.yourcampus.edu;
    root /path/to/campus-events-api-WEB303/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
Adjust the PHP-FPM socket path to match your server.

10. Cache for speed
Run these after every change to .env or the routes:
php artisan config:cache
php artisan route:cache
php artisan view:cache
The routes are safe to cache: the project has no closure routes (Laravel refuses to cache those). If you add a closure route later, route:cache will fail with a clear message. Use a controller method instead.
After editing .env, run php artisan config:clear first, then config:cache again. Otherwise Laravel keeps using the old values.

11. Check that it works
curl -I https://events.yourcampus.edu/up
/up is Laravel’s built-in health check and should return 200 OK.
Then log in through the API (see docs/API.md) and call GET /api/v1/categories with the token. You should get the category list.

12. Security checklist
    • ☐ APP_DEBUG=false and APP_ENV=production in the server’s .env
    • ☐ HTTPS is enforced, and HTTP redirects to HTTPS
    • ☐ No seeded test accounts (test@example.com, student@example.com) are on the server, or their passwords have been changed
    • ☐ The database user is not root, and its password is long and random
    • ☐ .env and .git can’t be downloaded from the web
    • ☐ Backups are scheduled (see below)
Known security gaps
These are not handled by the application yet. Plan for them before going live:
    • Tokens never expire. config/sanctum.php sets 'expiration' => null. To expire tokens, set it to a number of minutes, for example 60 * 24 * 7 for a week, then run php artisan config:cache.
    • No rate limiting on login. Someone can try passwords without being slowed down. You can add ->middleware('throttle:10,1') to the login route in routes/api.php.
    • No admin tooling. Roles can only be set in the database (see step 7).

13. Backups
Back up the database regularly. Example:
mysqldump -u campus_events_app -p campus_events > campus_events_$(date +%F).sql
Keep the backups somewhere other than the web server.

14. Logs
Application errors are written to storage/logs/laravel.log. Check it first when a request returns 500.
Keep the log file out of public access (it’s in storage/, which the web server shouldn’t serve).

15. Updating the application
    1. Put the new code on the server (keep the existing .env and storage/ folder).
    2. composer install --no-dev --optimize-autoloader
    3. php artisan migrate --force
    4. php artisan config:cache && php artisan route:cache && php artisan view:cache
If the front end changed, rebuild on your own computer (npm ci && npm run build) and copy the new public/build folder to the server. Node.js isn’t needed on the server.

Troubleshooting
Symptom	Likely cause	Fix
Blank page or 500	Error hidden by APP_DEBUG=false	Check storage/logs/laravel.log
419 or Permission denied on storage/	Wrong file permissions	Step 8
404 on every page except the home page	Web server not rewriting to index.php	Check .htaccess (Apache) or try_files (Nginx)
No application encryption key	APP_KEY missing	php artisan key:generate, then config:cache
Changes to .env have no effect	Old config cached	php artisan config:clear then config:cache
SQLSTATE[HY000] [1045]	Wrong database user or password	Check DB_USERNAME and DB_PASSWORD
Front-end API calls blocked from another domain	CORS not configured	Run php artisan config:publish cors, then add your domain to config/cors.php
Run php artisan config:publish cors, then add your domain to config/cors.php
