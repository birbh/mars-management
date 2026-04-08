# Mars Haven system

Mars Haven is a role based PHP and MySQL web app that improves monitoring a Mars habitat during diff. conditions.

The project was built as a full-stack application project....

## What it does???

Lets admins log new solar storm events.
Auto generates related radiation and power records from  intensity.
Shows live  update for astronauts and users through  dashboard refresh(used js).
Tracks emergency events when limits are crossed.
Shows the important datas in charts(used from::: https://www.chartjs.org).

also to improve exp. i have included sound effects (exploreee!!! and find)(hint:🚨->high intensity)

## Signup

New users can create an account from `signup.php` and choose either `user` or `astronaut`.
The public signup form does not allow `admin`.

## Roles and dashboards
### Admin
- Logs storm intensity and description.
- Triggers derived radiation status && power state 
- Can see recently logged storm records.

### Astronaut
- Sees latest radiation monitoring details.
- Sees recent power system logs.
- Gets system health output and warning messages.
- Dashboard auto refreshes .

### User
- Sees latest storm, radiation, and power snapshot.
- Dashboard auto-refreshes .

## Tech used

- Frontend: HTML, CSS, JavaScript
- Backend: PHP 
- Database: MySQL
- Local environment: XAMPP or 
visit: [This site](https://marshaven.byethost8.com)


## How to run locally (XAMPP)

1. Clone or copy this project into your XAMPP htdocs folder.
2. Start XAMPP systems.
3. Open phpMyAdmin and import `database/schema.sql`.
4. Confirm dbs connection in `config/db.php`.
5. then open:::`http://localhost/mars-management/login.php`

## login credential(for demo only)

You can log in using either username or email.

- Admin
Username: `admin`
Email: `admin@marshaven.local`
Password: `admin123`

- Astronaut
Username: `astronaut`
Email: `astro@marshaven.local`
Password: `astro123`

- User
Username: `user`
Email: `user@marshaven.local`
Password: `user123`


## note

This project is built as a practical combining backend logic, database modeling, and rolebased ui behavior in a single app.

