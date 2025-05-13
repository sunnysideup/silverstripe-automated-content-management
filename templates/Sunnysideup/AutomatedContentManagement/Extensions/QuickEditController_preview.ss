<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>$Title</title>
  <style>
    body {
      margin: 0;
      font-family: sans-serif;
      padding-bottom: 60px; /* space for footer buttons */
    }
    h1 {
      text-align: center;
      margin: 20px 0;
    }
    .columns {
      display: flex;
      gap: 20px;
      max-width: 800px;
      margin: 0 auto;
      padding: 0 20px;
    }
    .column {
      flex: 1;
      border: 1px solid #ccc;
      padding: 10px;
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
  </style>
</head>
<body>

  <h1>$Title (part of the $Instruction.Title instruction)</h1>

  <div class="columns">
    <div class="column">
      <h2>Before</h2>
      <div class="value">$Before</div>
    </div>
    <div class="column">
      <h2>After</h2>
      <div class="value">$After</div>
    </div>
  </div>

  <div class="footer-buttons">
    <button type="button">Accept</button>
    <button type="button">Decline</button>
  </div>

</body>
</html>
