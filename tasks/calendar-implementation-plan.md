# Calendar View Implementation Plan

## Overview
Build a full-featured calendar view using FullCalendar library to display tasks and events with filtering, drag-and-drop rescheduling, and event management.

## Database Schema (Already Exists)

### Events Table
- id, team_id, created_by
- title, description, location
- start_datetime, end_datetime, all_day
- color, type (event, meeting, appointment, reminder)
- status (scheduled, cancelled, completed)
- created_at, updated_at

### Event_Attendees Table
- id, event_id, user_id
- response_status (pending, accepted, declined, tentative)
- is_organizer, notes, responded_at
- created_at, updated_at

### Tasks Table (Existing)
- Has due_date field for calendar display

## Implementation Plan

### 1. Event Model (/var/www/models/Event.php)
- Create Event class with database operations
- Methods:
  - getEvents($teamId, $startDate, $endDate, $filters) - Get events for date range
  - getEventById($eventId) - Get single event
  - createEvent($data) - Create new event
  - updateEvent($eventId, $data) - Update event
  - deleteEvent($eventId) - Delete event
  - getEventAttendees($eventId) - Get attendees for event
  - addAttendee($eventId, $userId, $isOrganizer) - Add attendee
  - updateAttendeeResponse($eventId, $userId, $status) - Update response
  - removeAttendee($eventId, $userId) - Remove attendee

### 2. Directory Structure
```
/var/www/html/partials/calendar/
├── index.php                  # Main calendar view with FullCalendar
├── feed.php                   # JSON feed for calendar events
├── create-form.php            # Create event form modal
├── create.php                 # Create event endpoint
├── view.php                   # Event details modal
├── edit-form.php              # Edit event form modal
├── update.php                 # Update event endpoint
├── delete.php                 # Delete event endpoint
├── update-task-date.php       # Update task due date (drag-drop)
└── attendees/
    ├── add.php                # Add attendee endpoint
    ├── remove.php             # Remove attendee endpoint
    └── update-response.php    # Update attendee response
```

### 3. Main Calendar View (index.php)
- Include FullCalendar from CDN
- Bootstrap 5.3 layout with filters
- Calendar container with unique IDs
- Filter controls:
  - Toggle: Tasks only / Events only / Both
  - Team member filter (multi-select)
  - Event type filter (multi-select)
  - Priority filter (for tasks)
- FullCalendar configuration:
  - Month, week, day views
  - Event feed: /partials/calendar/feed.php
  - Event click: opens details modal
  - Event drop: updates task due_date or event dates
  - Date click: create new event
  - Color coding by priority/status
- Buttons:
  - Create Event button
  - Filter toggle buttons
  - View switcher (month/week/day)

### 4. Event Feed Endpoint (feed.php)
- GET request with parameters:
  - start: ISO date
  - end: ISO date
  - show: 'tasks', 'events', 'both' (default: both)
  - team_members: array of user IDs
  - event_types: array of event types
  - priorities: array of task priorities
- Query tasks and events from database
- Return JSON array of events:
  ```json
  [
    {
      "id": "task-123",
      "title": "Task Title",
      "start": "2025-11-20",
      "backgroundColor": "#3788d8",
      "borderColor": "#3788d8",
      "extendedProps": {
        "type": "task",
        "taskId": 123,
        "priority": "high",
        "status": "in_progress"
      }
    },
    {
      "id": "event-456",
      "title": "Meeting",
      "start": "2025-11-21T10:00:00",
      "end": "2025-11-21T11:00:00",
      "backgroundColor": "#28a745",
      "borderColor": "#28a745",
      "extendedProps": {
        "type": "event",
        "eventId": 456,
        "location": "Conference Room",
        "eventType": "meeting"
      }
    }
  ]
  ```

### 5. Event Creation
- Modal form (create-form.php):
  - Title (required)
  - Description (textarea)
  - Location
  - Start date/time (datetime-local)
  - End date/time (datetime-local)
  - All day checkbox
  - Event type (select: event, meeting, appointment, reminder)
  - Color picker (default colors)
  - Team members (multi-select for attendees)
- Endpoint (create.php):
  - POST with HTMX
  - Validate inputs
  - Create event in database
  - Add attendees (creator as organizer)
  - Log activity
  - Return success and refresh calendar

### 6. Event Details Modal (view.php)
- Display all event information:
  - Title, description, location
  - Start/end date/time
  - Event type, color
  - Status
  - Created by
- Show attendees list:
  - Name with avatar/initials
  - Response status (badge)
  - Organizer indicator
- Action buttons:
  - Edit (if user is organizer or admin)
  - Delete (if user is organizer or admin)
  - Update my response (if user is attendee)
  - Close modal

### 7. Event Edit
- Similar to create form
- Pre-populated with event data
- Update endpoint handles changes
- Can modify attendees
- Activity logging

### 8. Event Delete
- Confirmation dialog
- DELETE request via HTMX
- Remove event and attendees
- Log activity
- Refresh calendar

### 9. Attendee Management
- Add attendees to event
- Remove attendees (organizer only)
- Update response status (accept/decline/tentative)
- Send notifications (optional - log as activity)
- Display in event details

### 10. Task Drag-and-Drop
- FullCalendar eventDrop callback
- Update task due_date when dropped to new date
- HTMX POST to update-task-date.php
- Validate authorization (user owns or assigned)
- Log activity
- Show success message

### 11. Calendar Filters
- Toggle buttons for Tasks/Events/Both
- Multi-select for team members
- Multi-select for event types
- Priority filter (for tasks)
- Filters trigger HTMX request to reload calendar feed
- Maintain filter state in URL parameters

### 12. Color Coding
**Priority (Tasks):**
- Critical: #dc3545 (red)
- High: #fd7e14 (orange)
- Medium: #ffc107 (yellow)
- Low: #28a745 (green)

**Status (Tasks - alternate toggle):**
- Pending: #6c757d (gray)
- In Progress: #0dcaf0 (cyan)
- Review: #6f42c1 (purple)
- Completed: #28a745 (green)

**Event Types:**
- Event: #0d6efd (blue)
- Meeting: #198754 (green)
- Appointment: #0dcaf0 (cyan)
- Reminder: #ffc107 (yellow)

## Technical Requirements

### FullCalendar Integration
- CDN: https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js
- Plugins: dayGrid, timeGrid, interaction
- Configuration:
  - headerToolbar: prev, next, today, month/week/day views
  - editable: true (for drag-drop)
  - droppable: true
  - eventSources: /partials/calendar/feed.php
  - eventClick: open details modal
  - eventDrop: update task/event dates
  - dateClick: create new event

### HTMX Patterns
- Event feed loaded via fetch (not HTMX)
- Forms use hx-post for submission
- Modals use hx-get for content
- Calendar refresh via HTMX trigger
- Filter changes reload feed

### Security
- CSRF protection on all forms
- Authorization checks (user/team access)
- Input validation and sanitization
- XSS prevention (htmlspecialchars)
- SQL injection prevention (prepared statements)

### Activity Logging
- Event created/updated/deleted
- Task due date changed
- Attendee added/removed
- Response status updated

## Success Criteria
- Calendar displays with month/week/day views
- Tasks shown on due dates
- Events shown with start/end times
- Color-coded by priority or status
- Click opens details modal
- Drag-and-drop reschedules tasks
- Event creation works
- Attendee management works
- Filters work correctly
- Responsive on all devices
- All divs have unique IDs
- HTMX integration smooth

## Files to Create
1. /var/www/models/Event.php
2. /var/www/html/partials/calendar/index.php
3. /var/www/html/partials/calendar/feed.php
4. /var/www/html/partials/calendar/create-form.php
5. /var/www/html/partials/calendar/create.php
6. /var/www/html/partials/calendar/view.php
7. /var/www/html/partials/calendar/edit-form.php
8. /var/www/html/partials/calendar/update.php
9. /var/www/html/partials/calendar/delete.php
10. /var/www/html/partials/calendar/update-task-date.php
11. /var/www/html/partials/calendar/attendees/add.php
12. /var/www/html/partials/calendar/attendees/remove.php
13. /var/www/html/partials/calendar/attendees/update-response.php

## Notes
- Keep code simple and minimal
- Follow existing patterns from tasks and kanban
- Reuse existing modals and Bootstrap components
- Use existing Activity model for logging
- Match existing design and styling
- All queries use prepared statements
- User-friendly error messages
- Test all functionality
