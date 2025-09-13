<style>
        body {
            margin: 0;
            font-family: sans-serif;
            padding-bottom: 60px; /* space for footer buttons */
            max-width: 1400px;
            margin: 0 auto;
        }
        h1, h2 {
            text-align: center;
            padding-top: 2em;
        }
        .columns,
        .other-info {
            gap: 20px;
            margin: 0 auto;
        }
        .columns {
            display: flex;
        }
        .column {
            flex: 1;
            border: 1px solid #ccc;
            padding: 10px;
            border-radius: 5px;
            background: #f9f9f9;
        }
        .column h2 {
            margin-top: 0;
            font-size: 1.2rem;
            text-align: center;
        }
        .footer-buttons {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: #f9f9f9;
            padding: 10px 20px;
            box-shadow: 0 -1px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: center;
            gap: 10px;
        }
        button {
            padding: 10px 20px;
            font-size: 1rem;
            cursor: pointer;
        }
        pre {
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .good-button {
            background-color: #4CAF50; /* Green */

        }
        .warning-button {
            background-color:rgb(0, 102, 255); /* Orange */
        }
        .bad-button {
            background-color: #f44336; /* Red */
        }
        button {
            opacity: 0.8;
            transition: opacity 0.3s;
            border: none;
            border-radius: 5px;
            a {
               color: white;
               text-decoration: none;
            }
        }
        button:hover {
            opacity:1;
        }
        .list-of-classes {
            li {
                margin-top: 1em;
                margin-bottom: 1em;
            }
        }
    </style>
