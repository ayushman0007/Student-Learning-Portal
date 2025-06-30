# 🧑‍🎓 Student Learning & Test Management System

A web-based platform built with **PHP and MySQL** for managing student learning activities, tests, and discussions. It features a dual-module system:

- **User Dashboard** for students
- **Admin Panel** for managing users, tests, content, and performance reports

---

## 🚀 Features

### 👨‍🎓 User Dashboard
- 🔍 **Profile Details**: View and manage personal information
- 📁 **Documents**: Upload and view academic documents (PDF/images)
- 🧪 **Tests**:
  - Attempt tests based on availability
  - See results with GPA, grades, and downloadable reports
  - View previous test performance and grades
- 📚 **Study Materials**:
  - Access categorized resources by subject and topic
- 💬 **Discussion Forum**:
  - Post questions and answers
  - Engage in peer-to-peer discussion

### 🛠️ Admin Panel
- 📩 **Enquiry**: View and manage enquiry form submissions
- 👥 **Users**: Activate/deactivate user access, view full profiles
- 🧠 **Tests**:
  - Create and schedule tests
  - Add/edit questions with weightage
  - Notify users via email
- 🧾 **Test Results**:
  - View student responses and scores
  - Download exam reports in PDF
  - Generate certificates based on grades
- 📤 **Study Materials**:
  - Upload and manage files categorized by subject & topic

---

## 🛠️ Tech Stack

| Layer        | Technology           |
|--------------|----------------------|
| Frontend     | HTML, CSS, JavaScript, Bootstrap |
| Backend      | PHP                  |
| Database     | MySQL                |
| Tools Used   | XAMPP, phpMyAdmin    |

---

## 🗂️ Project Structure

/project-root
│
├── index.php # Landing page or enquiry form
├── signup.php # User registration
├── login.php # User login
├── profile.php # User dashboard
├── admin.php # Admin dashboard
├── submit_answer.php # Handles test responses
├── db/
│ └── database.sql # MySQL DB export
├── assets/ # CSS, JS, images
├── uploads/ # User documents
└── README.md # This file


---

## 📦 Setup Instructions

1. 📥 **Download & Install [XAMPP](https://www.apachefriends.org/)**
2. 📁 **Place the project folder inside:**

C:\xampp\htdocs\your-project-folder

3. 🧾 **Import the Database:**
- Start Apache & MySQL via XAMPP
- Open `http://localhost/phpmyadmin`
- Create a database (e.g. `student_system`)
- Use **Import** tab to upload `database.sql` file
4. 🌐 **Run the Application:**
- Visit `http://localhost/your-project-folder`

---

## 📄 License

This project is open-source and free for personal or academic use.

---

## 🤝 Contributing

Pull requests and suggestions are welcome. For major changes, please open an issue first.

---

