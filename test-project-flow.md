# Test Project Creation Flow

## Complete Flow Test Steps

To test the project creation flow and see all logs, follow these steps:

### Step 1: Create a new project through wizard
1. Visit `/projects/create`
2. Select Academic Level and Project Type (Step 1)
3. Fill Institution Details (Step 2) 
4. Complete Academic Configuration (Step 3)
5. Click "Complete Setup"

### Step 2: Topic Selection
1. Select or generate a topic
2. Submit topic selection

### Step 3: Topic Approval
1. Approve the selected topic
2. This should complete the flow

## Log Files to Monitor

Watch these logs during testing:

```bash
# Watch Laravel logs in real-time
tail -f storage/logs/laravel.log | grep "PROJECT"

# Or filter specific stages:
tail -f storage/logs/laravel.log | grep "PROJECT WIZARD"
tail -f storage/logs/laravel.log | grep "PROJECT COMPLETION"
tail -f storage/logs/laravel.log | grep "TOPIC"
```

## Expected Behavior

**Normal Flow (Should create only 1 final project):**
1. Step 1 save → Creates setup project #1 (status: setup, is_active: true)
2. Steps 2-3 saves → Updates the same project #1
3. Complete Setup → Converts project #1 to topic_selection status
4. Topic selection → Updates project #1 with topic info
5. Topic approval → Updates project #1 to topic_approved status

**Current Issue (Creating 3 projects):**
- Need to identify where extra projects are being created
- Logs will show the exact creation points

## Log Analysis

Look for these key log entries:
- `PROJECT WIZARD - Before Creating New Setup Project`
- `PROJECT WIZARD - Created New Setup Project`
- `PROJECT COMPLETION - Setup Analysis`
- `PROJECT COMPLETION - Using Existing Project` vs `No Active Setup Project Found`
- `TOPIC SELECTION - Topic Selected`
- `TOPIC APPROVAL - Processing Approval`