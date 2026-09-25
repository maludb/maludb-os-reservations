# Task List View Implementation Plan

## User Request
Build the task list view with active search, filtering, and sorting. Replace existing tables with HTMX-PHP backend and frontend patterns.

## Requirements

### 1. Task List View
- Display tasks in a table with sortable columns:
  - Title, Status, Priority, Assignee, Due Date, Category
- Row actions: Edit, Delete, Complete
- Status badges with colors
- Priority indicators with colored dots
- Highlight overdue tasks (red background)
- Pagination (20 tasks per page)

### 2. Active Search
- Search input with HTMX
- Trigger: `keyup changed delay:500ms`
- Target: `#task-list-table`
- Search by title and description

### 3. Filtering
- Filter by status (pending, in_progress, review, completed, cancelled)
- Filter by priority (low, medium, high, critical)
- Filter by assignee
- Filter by due date range
- Filters update via HTMX

### 4. Sorting
- Click column headers to sort
- Show sort direction indicators (↑↓)
- Maintain sort state in URL parameters

### 5. Bulk Operations
- Checkboxes for task selection
- Bulk action bar (appears when items selected)
- Actions: Complete, Delete, Change Status, Change Priority

## Implementation Plan

### Phase 1: Update Task Model (/var/www/models/Task.php)

Add the following methods:

1. **search($userId, $filters, $sort, $page, $perPage = 20)**
   - Parameters:
     - `$userId`: Current user ID
     - `$filters`: Array with keys: query, status, priority, assignee, date_from, date_to, team_id
     - `$sort`: Array with keys: column, direction
     - `$page`: Current page number
     - `$perPage`: Items per page
   - Returns: Array with 'tasks' and 'total' keys
   - SQL with prepared statements
   - Support for full-text search on title and description

2. **updateBulk($taskIds, $updates, $userId)**
   - Parameters:
     - `$taskIds`: Array of task IDs
     - `$updates`: Array of fields to update
     - `$userId`: For authorization
   - Returns: Number of updated tasks
   - Verify user owns/assigned to tasks

3. **deleteBulk($taskIds, $userId)**
   - Delete multiple tasks at once
   - Authorization check

### Phase 2: Create Helper Functions

**File: /var/www/helpers/ui.php**

1. `getStatusBadge($status)` - Return Bootstrap badge HTML
   - pending: warning (orange)
   - in_progress: info (blue)
   - review: primary (purple)
   - completed: success (green)
   - cancelled: secondary (gray)

2. `getPriorityDot($priority)` - Return colored dot indicator
   - low: success (green)
   - medium: info (blue)
   - high: warning (orange)
   - critical: danger (red)

3. `isOverdue($dueDate, $status)` - Check if task is overdue
   - Return true if due_date < today AND status NOT IN (completed, cancelled)

**File: /var/www/helpers/date.php** (already planned, verify exists)

1. `formatDate($date)` - Format date nicely
2. `timeAgo($timestamp)` - Convert to "2 hours ago"

### Phase 3: Create Task List Partial

**File: /var/www/html/partials/tasks/list.php**

Main endpoint that handles both initial load and HTMX updates.

Structure:
1. Auth check
2. Get parameters (search, filters, sort, page)
3. Load Task model
4. Call search() method
5. Render table or full page depending on request

**File: /var/www/html/partials/tasks/index.php**

Complete page with:
- Search bar (ID: `#task-search-input`)
- Filter controls (ID: `#task-filters`)
- Task table container (ID: `#task-list-container`)
- Pagination (ID: `#task-pagination`)
- Bulk action bar (ID: `#task-bulk-actions`)

### Phase 4: Create Table Component

**File: /var/www/html/partials/tasks/table.php**

Returns just the table HTML for HTMX swaps.

Features:
- Responsive Bootstrap table
- Sortable column headers with icons
- Status badges
- Priority dots
- Assignee names
- Formatted dates
- Row actions (Edit, Delete, Complete)
- Overdue highlighting
- Checkbox for bulk selection

### Phase 5: Create Action Endpoints

1. **partials/tasks/bulk-action.php**
   - POST endpoint
   - Handle: complete, delete, change_status, change_priority
   - Return updated table

2. **partials/tasks/complete.php**
   - POST: Mark task as completed
   - Return updated row

3. **partials/tasks/delete.php**
   - DELETE/POST: Delete single task
   - Return empty (for swap outerHTML)

### Phase 6: Create Filter Components

**File: /var/www/html/partials/tasks/filters.php**

Filter form with:
- Status dropdown (multi-select or single)
- Priority dropdown
- Assignee dropdown (load from users table)
- Date range picker
- Clear filters button

All with HTMX attributes to update table on change.

### Phase 7: JavaScript Enhancements

Add to page or inline:

1. Bulk selection handler
   - "Select All" checkbox
   - Show/hide bulk action bar
   - Track selected count

2. Sort state management
   - Toggle sort direction on click
   - Update URL parameters

3. Pagination state
   - Preserve filters/search when changing pages

## File Structure

```
/var/www/
├── models/
│   └── Task.php (update)
├── helpers/
│   ├── ui.php (create)
│   └── date.php (verify/create)
└── html/
    └── partials/
        └── tasks/
            ├── index.php (create - main page)
            ├── list.php (create - dual endpoint)
            ├── table.php (create - table HTML)
            ├── filters.php (create - filter controls)
            ├── bulk-action.php (create)
            ├── complete.php (create)
            └── delete.php (update if exists)
```

## Design References

### Status Colors (from design-notes.md if exists)
- Pending: `badge bg-warning text-dark`
- In Progress: `badge bg-info text-white`
- Review: `badge bg-primary text-white`
- Completed: `badge bg-success text-white`
- Cancelled: `badge bg-secondary text-white`

### Priority Colors
- Low: `text-success` / green dot
- Medium: `text-info` / blue dot
- High: `text-warning` / orange dot
- Critical: `text-danger` / red dot

### Table Classes
```html
<table class="table table-hover table-striped">
  <thead class="table-light">
    <!-- sortable headers -->
  </thead>
  <tbody>
    <!-- task rows -->
  </tbody>
</table>
```

### Overdue Row
```html
<tr class="table-danger">
  <!-- task is overdue -->
</tr>
```

## HTMX Patterns to Use

### Search Input
```html
<input type="search"
       id="task-search-input"
       name="q"
       class="form-control"
       placeholder="Search tasks..."
       hx-get="/partials/tasks/table.php"
       hx-trigger="keyup changed delay:500ms"
       hx-target="#task-list-table"
       hx-include="#task-filters input, #task-filters select">
```

### Filter Select
```html
<select name="status"
        class="form-select"
        hx-get="/partials/tasks/table.php"
        hx-trigger="change"
        hx-target="#task-list-table"
        hx-include="#task-search-input, #task-filters input, #task-filters select">
```

### Sortable Column
```html
<th>
  <a href="#"
     hx-get="/partials/tasks/table.php?sort=title&dir=asc"
     hx-target="#task-list-table"
     hx-include="#task-search-input, #task-filters input, #task-filters select">
    Title <i class="bi bi-arrow-up"></i>
  </a>
</th>
```

### Pagination
```html
<nav>
  <ul class="pagination">
    <li class="page-item">
      <a class="page-link"
         href="#"
         hx-get="/partials/tasks/table.php?page=2"
         hx-target="#task-list-container"
         hx-include="#task-search-input, #task-filters input, #task-filters select">
        2
      </a>
    </li>
  </ul>
</nav>
```

### Bulk Actions
```html
<form id="bulk-form">
  <div class="btn-group">
    <button hx-post="/partials/tasks/bulk-action.php"
            hx-vals='{"action": "complete"}'
            hx-include="[name='task_ids[]']:checked"
            hx-target="#task-list-table">
      Mark Complete
    </button>
  </div>
</form>
```

## Security Considerations

1. **Authentication**: Every partial starts with `check_auth()`
2. **Authorization**: Verify user owns/assigned to tasks before actions
3. **SQL Injection**: Use prepared statements for all queries
4. **XSS**: Use `htmlspecialchars()` for all output
5. **CSRF**: Add token to bulk action forms

## Testing Checklist

- [ ] Search works and debounces properly
- [ ] All filters work individually
- [ ] Multiple filters work together
- [ ] Sorting toggles ascending/descending
- [ ] Pagination preserves filters and search
- [ ] Bulk select all works
- [ ] Bulk actions work (complete, delete, change status/priority)
- [ ] Overdue tasks highlighted
- [ ] Status badges show correct colors
- [ ] Priority dots show correct colors
- [ ] Edit/Delete/Complete row actions work
- [ ] Responsive design works on mobile
- [ ] No SQL injection vulnerabilities
- [ ] No XSS vulnerabilities
- [ ] Proper authorization checks

## Success Criteria

- ✅ Task list displays with all columns
- ✅ Search updates table in real-time
- ✅ All filters work independently and together
- ✅ Sorting works on all columns
- ✅ Pagination works (20 per page)
- ✅ Bulk operations work correctly
- ✅ Overdue tasks are highlighted
- ✅ No page reloads (pure HTMX)
- ✅ Proper security measures in place
- ✅ Clean, maintainable code

## Notes

- Keep it simple - no complex JavaScript frameworks
- Follow existing Bootstrap theme
- Match existing design patterns from app.php
- Use existing helper functions where possible
- Every div needs unique ID for reference
- Document all changes in docs/activity.md
