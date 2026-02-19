# Admin Enrollment Control - Setup Guide

## Overview
This guide explains how to set up and use the new admin enrollment approval system for the Computer Coaching Platform.

## Database Migration

Before using the admin enrollment control features, you need to run the database migration to add approval tracking columns to the enrollments table.

### Step 1: Run the Migration
1. Open **phpMyAdmin** or your preferred MySQL client
2. Navigate to your database (e.g., `youtubedata`)
3. Open the **SQL** tab
4. Copy and paste the contents of `database/add_enrollment_approval.sql`
5. Click **Go** to execute the migration

### What Gets Created
The migration adds the following columns to the `enrollments` table:
- `approval_status` - Tracks enrollment status: `pending`, `approved`, or `rejected`
- `approved_by` - ID of the admin who approved/rejected the enrollment
- `approved_at` - Timestamp of when approval action was taken
- `rejection_reason` - Optional reason for rejection

## How It Works

### Current Behavior
By default, when students enroll in a course:
1. The enrollment is **automatically approved** and marked as `approved`
2. Students can immediately access the course
3. Admins can see all enrollments in the admin panel and manage them

### Admin Enrollment Management
Admins can now:
1. **View all enrollments** - See pending, approved, and rejected enrollments
2. **Filter enrollments** - By status (pending, approved, rejected) or search by student name/email/course
3. **Approve enrollments** - Mark as approved (updates approval_status and approval timestamp)
4. **Reject enrollments** - Reject with optional reason for denial
5. **Remove enrollments** - Permanently delete enrollment records

## Accessing Admin Enrollment Control

1. Log in as an admin user
2. Go to **Admin Dashboard**
3. Click **Manage Enrollments** or navigate to `/admin/enrollments.php`
4. You'll see statistics and a list of all enrollments

## Admin Actions

### Approve an Enrollment
- Click the **Approve** button next to a pending enrollment
- The enrollment will be marked as approved
- Admin name and timestamp will be recorded

### Reject an Enrollment
- Click the **Reject** button next to a pending enrollment
- A modal will appear asking for a rejection reason (optional)
- The student's enrollment will be marked as rejected
- The rejection reason will be stored and visible to admins

### Remove an Enrollment
- Click the **Remove** button to delete the enrollment record
- A confirmation dialog will appear
- The enrollment will be permanently deleted

## Filter & Search Options

### Status Filter
- **All** - Show all enrollments regardless of status
- **Pending** - Show enrollments waiting for approval
- **Approved** - Show approved enrollments
- **Rejected** - Show rejected enrollments

### Search
Search by:
- Student name
- Student email
- Course title

## Statistics Dashboard

The enrollments page displays:
- **Total** - Total number of enrollments
- **Pending** - Enrollments awaiting approval
- **Approved** - Approved enrollments
- **Rejected** - Rejected enrollments

## Future Enhancements

Possible future features:
1. **Enrollment approval requirement** - Option to require admin approval before enrollment access
2. **Bulk actions** - Approve/reject multiple enrollments at once
3. **Enrollment notifications** - Notify students of approval/rejection status
4. **Approval workflow** - Custom approval process with multiple stages
5. **Export functionality** - Export enrollment data to CSV/Excel

## Troubleshooting

### "Column doesn't exist" Error
- The migration hasn't been run yet
- Go to Step 1 of Database Migration above and run the SQL

### Can't see enrollments page
- Make sure you're logged in as an admin
- Visit `/admin/enrollments.php` directly or use the navigation menu

### Changes not saving
- Check database permissions
- Verify the database connection in `config/database.php`

## Notes

- The approval system is non-intrusive - it allows admins to track and manage enrollments without affecting student access
- By default, all enrollments are auto-approved to maintain backward compatibility
- Admins can always change this behavior by using the reject/remove functions
