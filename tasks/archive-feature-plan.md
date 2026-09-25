# Archive Tasks Feature Plan

## Overview
Add an "archived" status to tasks and replace the delete button with an archive button for completed tasks. Archived tasks should not appear in any list or count.

## Current State
- ✅ Task model has complete CRUD methods
- ✅ Delete functionality exists across all task views
- ✅ Activity logging system in place
- ✅ UI helpers for status styling
- ⚠️ Need: Add 'archived' status value
- ⚠️ Need: Replace delete with archive endpoint
- ⚠️ Need: Exclude archived tasks from all lists/counts

## Implementation Approach
Keep changes minimal and focused on two main areas:
1. **Backend**: Add archive method, exclude archived from queries
2. **Frontend**: Change delete button to archive, update confirmations

## Todo Items

### Phase 1: Database Schema
- [ ] Add 'archived' to valid task status values (modify status column type or add validation)
- [ ] No schema migration needed - just allow 'archived' as valid status value

### Phase 2: Task Model Updates (/var/www/models/Task.php)
- [ ] Modify `search()` method: Add WHERE clause to exclude archived tasks
- [ ] Modify `searchTeamTasks()` method: Add WHERE clause to exclude archived tasks
- [ ] Modify `getUpcomingTasks()` method: Exclude archived tasks
- [ ] Modify `getRecentTasks()` method: Exclude archived tasks
- [ ] Modify `getTaskStats()` method: Exclude archived tasks from counts
- [ ] Modify `getOverdueTasksCount()` method: Already excludes completed
- [ ] Replace `delete()` method with `archive()` method:
  - Set status to 'archived'
  - Update archived_at timestamp
  - Log activity as 'task_archived'
- [ ] Modify `deleteBulk()` to `archiveBulk()`:
  - Bulk archive instead of delete
  - Same archive logic
- [ ] Add `getArchivedTasks()` method (optional - for future archive view)

### Phase 3: UI Helper Updates (/var/www/helpers/ui.php)
- [ ] Add 'archived' case to `getStatusBadge()` function
- [ ] Add 'archived' case to `getStatusIcon()` function
- [ ] Add 'archived' case to `getStatusColor()` function

### Phase 4: Archive Endpoint (/var/www/html/partials/tasks/delete.php)
- [ ] Rename functionality from delete to archive (keep filename for now)
- [ ] Replace `Task->delete()` call with `Task->archive()`
- [ ] Change activity log type to 'task_archived'
- [ ] Update HX-Trigger response message

### Phase 5: Bulk Action Endpoint (/var/www/html/partials/tasks/bulk-action.php)
- [ ] Modify 'delete' case to call `Task->archiveBulk()` instead of `deleteBulk()`
- [ ] Update confirmation message

### Phase 6: Task Table Views
Update all task table displays to change button text and ensure archived tasks are excluded:

#### /var/www/html/partials/tasks/table.php
- [ ] Verify search excludes archived tasks (already done in Phase 2)
- [ ] Change delete button label: "Archive" (for completed tasks only)
- [ ] Change hx-delete to hx-post with archive endpoint
- [ ] Update hx-confirm message to "Archive this task?"

#### /var/www/html/partials/tasks/my-tasks-table.php
- [ ] Same changes as table.php

#### /var/www/html/partials/tasks/team-tasks-table.php
- [ ] Same changes as table.php

### Phase 7: Task Detail View (/var/www/html/partials/tasks/view.php)
- [ ] Change delete button to archive button (only show if status is 'completed')
- [ ] Update button text and confirmation message

### Phase 8: Kanban Board Views
#### /var/www/html/partials/kanban/board.php
- [ ] Exclude archived status from board (don't display archived column)
- [ ] Only show: pending, in_progress, review, completed

#### /var/www/html/partials/kanban/card.php
- [ ] Change delete button to archive button
- [ ] Update text and confirmation

### Phase 9: Bulk Actions JavaScript (/var/www/html/partials/tasks/index.php)
- [ ] Update bulk action form handling for 'archive' action
- [ ] No structural changes needed

### Phase 10: Activity Logging (/var/www/models/Activity.php)
- [ ] Ensure 'task_archived' activity type is supported (should already work)
- [ ] No changes needed - Activity model is generic

### Phase 11: Documentation & Testing
- [ ] Test that archived tasks don't appear in:
  - Main task list
  - My Tasks list
  - Team Tasks list
  - Kanban board
  - Upcoming tasks
  - Overdue tasks
  - Task counts/stats
- [ ] Test that archive button appears for completed tasks
- [ ] Test that archive action works (task removed from view)
- [ ] Test that bulk archive works
- [ ] Test that activity is logged correctly
- [ ] Verify authorization still works (only owner/creator can archive)

### Phase 12: Final Steps
- [ ] Update docs/activity.md with summary of changes
- [ ] Create git commit with all changes
- [ ] Push to remote

## File Structure Changes

### Files to Modify (No new files)
```
/var/www/models/
├── Task.php                          # Archive methods, exclude archived queries

/var/www/helpers/
├── ui.php                            # Add archived status styling

/var/www/html/partials/tasks/
├── delete.php                        # Archive instead of delete
├── table.php                         # Change button to archive
├── my-tasks-table.php                # Change button to archive
├── team-tasks-table.php              # Change button to archive
├── view.php                          # Change button to archive
├── bulk-action.php                   # Archive instead of delete
└── index.php                         # Update bulk action handling (if needed)

/var/www/html/partials/kanban/
├── board.php                         # Exclude archived column
└── card.php                          # Change button to archive
```

## Key Changes Summary

### Database Level
- Status values: 'pending', 'in_progress', 'review', 'completed', 'cancelled', 'archived'
- No new columns needed

### Backend Changes
1. Task.php: Add archive() method, exclude archived in all search queries
2. ui.php: Add 'archived' status styling
3. delete.php: Call archive() instead of delete()
4. bulk-action.php: Call archiveBulk() instead of deleteBulk()

### Frontend Changes
1. All task views: Remove delete button, add archive button for completed tasks
2. Update button text: "Archive" instead of "Delete"
3. Update confirmation: "Archive this task?" instead of "Delete this task?"
4. Kanban board: Exclude archived column from board display

## Success Criteria
- ✅ Archive button appears for all tasks (replacing delete)
- ✅ Clicking archive removes task from view
- ✅ Archived tasks don't appear in any list
- ✅ Archived tasks not included in counts
- ✅ Activity logged when task archived
- ✅ Authorization still enforced
- ✅ Bulk archive works
- ✅ Kanban board doesn't show archived column

## Design Notes
- Use same button styling as delete button
- Keep simple: just mark as archived, don't delete
- No "restore from archive" feature in initial implementation
- Archive is permanent - no undo button

## Technical Notes
- Use prepared statements (already in use)
- No SQL injection risk with status values
- XSS protection already in place
- CSRF tokens already in place
- Authorization checks already in place

## Rollback Plan
If needed, simply revert the status field and restore delete functionality. All changes are non-destructive to schema.
