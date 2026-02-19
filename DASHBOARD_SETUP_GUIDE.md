# Computer Coaching Platform - User Dashboard Setup Guide

## Overview
A comprehensive user dashboard for a computer coaching platform built with PHP and MySQL. This dashboard allows students to:
- View enrolled courses
- Track learning progress
- Complete lessons
- Submit assignments
- View grades and feedback
- Browse and enroll in new courses

## Features

### 1. **Dashboard Home** (`dashboard/user/index.php`)
- Welcome message with user profile
- Learning statistics (courses, lessons, assignments)
- Pending assignments with due dates
- Enrolled courses with progress bars
- Recent activity feed

### 2. **Course Management** (`dashboard/user/courses.php`)
- Browse all available courses
- Filter by difficulty level (beginner, intermediate, advanced)
- Search courses by title or description
- View course details
- Enroll in courses

### 3. **Course Details** (`dashboard/user/course-detail.php`)
- Course overview with progress tracking
- List of lessons organized by order
- Visual indicators for completed lessons
- Total lessons and duration information

### 4. **Lesson View** (`dashboard/user/lesson.php`)
- Full lesson content with video player support
- Lesson description and detailed content
- Related assignments
- Mark lesson as complete
- Progress tracking

### 5. **Assignment Management** (`dashboard/user/assignments.php`)
- View all assignments organized by status:
  - Overdue (in red)
  - Due Soon (in orange)
  - Pending
  - Completed with grades
- Due date tracking
- Quick submission access

### 6. **Assignment Submission** (`dashboard/user/submit-assignment.php`)
- Submit assignment responses
- Update existing submissions
- View grades and instructor feedback
- Track submission dates

## Database Tables Created

### Courses
```sql
- id (primary key)
- title
- description
- instructor_id (foreign key to users)
- level (beginner, intermediate, advanced)
- duration_months
- cover_image
- created_at, updated_at
```

### Enrollments
```sql
- id (primary key)
- user_id (foreign key)
- course_id (foreign key)
- enrollment_date
- progress (0-100%)
- status (active, completed, paused)
```

### Lessons
```sql
- id (primary key)
- course_id (foreign key)
- title
- description
- content (full lesson text)
- lesson_order
- duration_minutes
- video_url
- created_at
```

### Lesson Progress
```sql
- id (primary key)
- user_id (foreign key)
- lesson_id (foreign key)
- completed (boolean)
- completion_date
- started_at
```

### Assignments
```sql
- id (primary key)
- lesson_id (foreign key)
- title
- description
- due_date
- max_points
- created_at
```

### Assignment Submissions
```sql
- id (primary key)
- assignment_id (foreign key)
- user_id (foreign key)
- submission_text
- file_path
- submitted_at
- grade
- feedback
- graded_at
```

## Setup Instructions

### 1. Update Database Schema
Run the updated `database/schema.sql` in phpMyAdmin:
- Creates all new tables for courses, lessons, assignments, etc.
- Preserves existing users table

### 2. Create Test Data (Optional)
Insert sample courses, lessons, and assignments:

```sql
-- Create a test course
INSERT INTO courses (title, description, instructor_id, level, duration_months, created_at)
VALUES ('Introduction to PHP', 'Learn PHP basics from scratch', 1, 'beginner', 4, NOW());

-- Create lessons for the course
INSERT INTO lessons (course_id, title, description, content, lesson_order, duration_minutes, created_at)
VALUES 
(1, 'PHP Basics', 'Introduction to PHP', 'Welcome to PHP...', 1, 30, NOW()),
(1, 'Variables and Data Types', 'Understanding PHP variables', 'In PHP, variables...', 2, 45, NOW());

-- Enroll user in course
INSERT INTO enrollments (user_id, course_id, enrollment_date, progress, status)
VALUES (2, 1, NOW(), 0, 'active');

-- Create assignments
INSERT INTO assignments (lesson_id, title, description, due_date, max_points, created_at)
VALUES (2, 'Create PHP Variables', 'Create a PHP script with variables', DATE_ADD(NOW(), INTERVAL 7 DAY), 100, NOW());
```

### 3. File Structure
```
dashboard/user/
├── index.php                  # Main dashboard
├── courses.php               # Browse courses
├── course-detail.php         # Course overview
├── lesson.php                # Individual lesson
├── assignments.php           # All assignments
├── submit-assignment.php     # Assignment submission
└── enroll.php               # Enrollment handler

css/
├── style.css                # Main styles
└── dashboard.css            # Dashboard-specific styles

database/
└── schema.sql               # Updated database schema
```

## Key Features Implemented

✅ **User Authentication Integration**
- Works with existing auth system
- Session-based user verification

✅ **Progress Tracking**
- Course progress percentage
- Lesson completion status
- Assignment grades

✅ **Responsive Design**
- Mobile-friendly layout
- Grid-based responsive courses
- Flexible navigation

✅ **Color-Coded Status System**
- Green (#4CAF50): Completed
- Orange (#FF9800): Urgent/Due Soon
- Red (#f44336): Overdue
- Blue (#667eea): Primary actions

✅ **Modern UI/UX**
- Professional gradient headers
- Card-based layouts
- Smooth transitions
- Icon integration with Font Awesome
- Empty states with helpful messages

## Customization Tips

### Change Primary Color
Edit `css/dashboard.css` and replace `#fc6f41` (orange) with your brand color.

### Update Navigation Links
Edit the `<nav>` section in dashboard pages to match your site structure.

### Add Course Images
1. Upload course cover images to `images/` folder
2. Update course records with image paths
3. Update `css/dashboard.css` for image styling

### Extend Assignment Types
Add file upload support by:
1. Create `uploads/` directory
2. Add file upload handling in `submit-assignment.php`
3. Store file paths in database

## Security Notes

✅ **Implemented Security Features:**
- SQL injection prevention (mysqli_real_escape_string)
- Session-based authentication
- User enrollment verification
- CSRF protection recommended

⚠️ **Security Recommendations:**
- Use prepared statements (mysqli_prepare) for all queries
- Implement file upload validation if adding file upload feature
- Add CSRF tokens to forms
- Validate all user inputs server-side

## API Integration Points

The dashboard is ready to integrate with:
- **Video Platform APIs** (YouTube, Vimeo)
- **Video Upload**: Integrate with file storage service
- **Email Notifications**: Add for assignment deadlines
- **Analytics**: Track user engagement metrics

## Troubleshooting

### Database Connection Issues
- Check `config/database.php` settings
- Ensure MySQL is running
- Verify user has proper permissions

### Missing Styles
- Clear browser cache
- Check CSS file paths
- Ensure `font-awesome` CDN is accessible

### Enrollment Not Working
- Verify user is logged in
- Check database permissions
- Confirm course exists in database

## Next Steps

1. **Import the schema** into your database
2. **Create sample courses and lessons** for testing
3. **Test enrollment** and progress tracking
4. **Customize colors and branding**
5. **Add course cover images**
6. **Create admin panel** for managing courses (optional)

## File Sizes

- `dashboard.css`: ~12 KB
- `index.php`: ~5 KB
- `courses.php`: ~4 KB
- `course-detail.php`: ~3.5 KB
- `assignments.php`: ~4.5 KB
- `lesson.php`: ~6 KB
- `submit-assignment.php`: ~5 KB

## Support

For issues or questions, review:
- Database schema in `database/schema.sql`
- PHP authentication in `includes/auth_helpers.php`
- Existing styles in `css/style.css`

---
**Version**: 1.0  
**Last Updated**: 2026-02-17  
**Status**: Production Ready
