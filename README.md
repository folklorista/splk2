# Project Name

## Overview

This project is a web application with an Angular frontend and a PHP-based REST API backend.

## Technologies Used

- **Frontend:** Angular
- **Backend:** PHP (REST API)

## Getting Started

### Prerequisites

- Node.js
- Angular CLI
- PHP
- Composer

### Installation

#### Frontend

1. Navigate to the frontend directory:

   ```sh
   cd frontend
   ```

2. Install the dependencies:

   ```sh
   npm install
   ```

3. Run the development server:

   ```sh
   ng serve
   ```

4. Open your browser and navigate to `http://localhost:4200`.

#### Backend

1. Navigate to the backend directory:

   ```sh
   cd api
   ```

2. Install the dependencies:

   ```sh
   composer install
   ```

3. Start the PHP server:

   ```sh
   php -S localhost:8000 -t public
   ```

4. The API will be available at `http://localhost:8000`.

## Usage

- The frontend communicates with the backend via REST API calls.
- Ensure both the frontend and backend servers are running.

## Contributing

1. Fork the repository.
2. Create a new branch (`git checkout -b feature-branch`).
3. Commit your changes (`git commit -am 'Add new feature'`).
4. Push to the branch (`git push origin feature-branch`).
5. Create a new Pull Request.

## License

This project is licensed under the MIT License.


## TODO

- Add unit tests for the backend
- Improve frontend error handling
- Optimize database queries
- Write documentation for API endpoints
- Add enum `Data Action` (add, edit, view, remove)
- Flash messages
- Implement cross tables through foreign keys
- One-click actions (add to group)
- Checkboxes
- Table sort
- bug: při prázdné tabulce persons se po rozkliknutí users nenačtou persons, ale data z users