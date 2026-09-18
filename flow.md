USER flow
1. landing screen which consists of the login/register page and also information on the app
2. Login/Register
3. Directed to their Dashboard

Dashboard flow
1. greeting from the system
2. section to show what the user can do first
3. the rest of the questionnaires

SIDENOTES:
CV builder


NOTES:
-- reduce the amount of words and go for the least resistant path to complete the task.

______________________________________________________________________________________________________
DATABASE
TABLES

USER
- userID
- firstName 
- lastName 
- email
- phoneNumber
- grade ENUM ('grade 9', 'grade 10', 'grade 11', 'grade 12', 'out of school')
- passwordHash
- consentAt
- tempToken //user MFA
- tempTokenExpiry
- createdAt

careers -- taken from the web using scrapers
- careerID
- careerName
- careerField
- salaryMin
- salaryMax
- description
- demand ENUM ('high', 'medium', 'low')
- mathTrack
- minAPS
- ofoCode //source code from NCAP/DHET for traceability
- sourceUrl //where the scraper pulled this from
- scrapedAt

qualifications -- what a career leads to
- qualificationID
- title
- nqfLevel
- duration
- entryAPS

providers -- institutions offering qualifications
- providerID
- name
- type ENUM ('university', 'tvet', 'cet', 'private', 'seta')
- nsfasAccredited BOOLEAN

careerQualifications -- join: a career can lead to several qualifications
- careerID
- qualificationID

qualificationProviders -- join: a qualification can be offered by several providers
- qualificationID
- providerID

assessmentResults -- one row per completed questionnaire, never overwritten
- assessmentID
- userID
- source ENUM ('subject_chooser', 'career_choice', 'job_fit')
- payload //answers given
- derived //computed score/result
- createdAt

favourites -- careers a user has saved
- favouriteID
- userID
- careerID
- createdAt