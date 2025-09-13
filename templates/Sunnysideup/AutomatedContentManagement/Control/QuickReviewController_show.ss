<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="$BaseURL" />
    <title>$Title</title>
    <% include Sunnysideup/AutomatedContentManagement/Control/Includes/Styles %>
</head>
<body>
    <h1>$Title</h1>
    <h3>Select updates made by the LLM Editor</h3>
    <ul class="list-of-classes">
    <% loop $ListOfClasses %>
        <li>
            <a href="$Link">$Name</a>
            <% if $Fields %>
                <ul>
                <% loop $Fields %>
                    <li>
                       $Name
                        <br /><a href="$Link#original-updated">original already updated</a> | <a href="$Link#answers-completed">answers completed only</a>
                    </li>
                <% end_loop %>
                </ul>
            <% end_if %>
        </li>
    <% end_loop %>
    </ul>


    <h3>Review updates</h3>
    <div>

        <% if $ListOriginalUpdated %>
        <div class="list" id="original-updated">
            <h3>Recently updated records ($ListOriginalUpdated.count)</h3>
            <ul>
                <% loop $ListOriginalUpdated %>
                    <li>
                        <% if $RecordLinkView %>
                            <a href="$RecordLinkView" target="_blank">[View]</a>
                        <% end_if %>
                        <a href="$Link">$Title</a>
                        <blockquote>
                            $ShortenedAnswer
                        </blockquote>
                    </li>
                <% end_loop %>
            </ul>
        </div>
        <% end_if %>

        <% if $ListAnswerCompleted %>
        <div class="list" id="answers-completed">
            <h3>Recently completed answers from LLM ready to be reviewed ($ListAnswerCompleted.count)</h3>
            <ul>
                <% loop $ListAnswerCompleted %>
                    <li>
                        <a href="$Link">$Title</a>
                        <blockquote>
                            $ShortenedAnswer
                        </blockquote>
                    </li>
                <% end_loop %>
            </ul>
        </div>
        <% end_if %>
    </div>

</body>
</html>
