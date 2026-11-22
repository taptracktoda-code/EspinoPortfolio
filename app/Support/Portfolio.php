<?php

namespace App\Support;

final class Portfolio
{
    public const SECTIONS = [
        'projects' => [
            'label' => 'Projects', 'empty' => 'No Projects Available', 'item' => 'Project',
            'fields' => [
                'overview' => ['Overview', 'text', 'required'],
                'description' => ['Detailed description', 'textarea'],
                'category' => ['Category', 'select', 'required', ['Web', 'Mobile', 'Academic', 'Personal']],
                'role' => ['Role', 'text'], 'technologies' => ['Technology stack (comma separated)', 'text'],
                'features' => ['Features', 'textarea'],
                'status' => ['Project status', 'select', 'required', ['Planned', 'Ongoing', 'Completed']],
                'github' => ['GitHub URL', 'url'], 'demo' => ['Live demo URL', 'url'],
                'documentation' => ['Documentation URL', 'url'], 'featured' => ['Featured project', 'checkbox'],
            ],
            'upload' => ['Project screenshots', 'jpg,jpeg,png,webp', 5120, 8],
        ],
        'certificates' => [
            'label' => 'Certificates', 'empty' => 'No Certificates Available', 'item' => 'Certificate',
            'fields' => [
                'organization' => ['Issuing organization', 'text', 'required'],
                'issued' => ['Date issued', 'date', 'required'], 'expires' => ['Expiration date', 'date'],
                'category' => ['Category', 'text'], 'description' => ['Description', 'textarea'],
                'verification' => ['Verification URL', 'url'],
            ],
            'upload' => ['Certificate file', 'pdf,jpg,jpeg,png,webp', 10240, 1],
        ],
        'achievements' => [
            'label' => 'Achievements', 'empty' => 'No Achievements Available', 'item' => 'Achievement',
            'fields' => [
                'organization' => ['Organization', 'text'], 'date' => ['Date', 'date'],
                'category' => ['Category', 'text'], 'description' => ['Description', 'textarea'],
            ],
            'upload' => ['Supporting media', 'pdf,jpg,jpeg,png,webp', 10240, 4],
        ],
        'experiences' => [
            'label' => 'Experience', 'empty' => 'No Experience Available', 'item' => 'Position',
            'fields' => [
                'organization' => ['Organization', 'text', 'required'],
                'category' => ['Type', 'select', 'required', ['Employment', 'Internship', 'OJT', 'Freelance', 'Volunteer', 'Other']],
                'start' => ['Start date', 'date'], 'end' => ['End date', 'date'],
                'responsibilities' => ['Responsibilities', 'textarea'], 'technologies' => ['Technologies', 'text'],
                'accomplishments' => ['Accomplishments', 'textarea'],
            ],
        ],
        'educations' => [
            'label' => 'Education', 'empty' => 'No Education Records Available', 'item' => 'Degree',
            'fields' => [
                'organization' => ['Institution', 'text', 'required'], 'field' => ['Field of study', 'text'],
                'start' => ['Start date', 'date'], 'end' => ['End date', 'date'], 'description' => ['Description', 'textarea'],
            ],
            'upload' => ['Institution logo', 'jpg,jpeg,png,webp', 5120, 1],
        ],
        'skills' => [
            'label' => 'Skills', 'empty' => 'No Skills Available', 'item' => 'Skill',
            'fields' => ['category' => ['Skill category', 'category', 'required']],
        ],
        'resumes' => [
            'label' => 'Resume', 'empty' => 'Resume Not Available', 'item' => 'Resume version', 'fields' => [],
            'upload' => ['Resume PDF', 'pdf', 10240, 1],
        ],
        'social-links' => [
            'label' => 'Social links', 'empty' => 'No Social Links Available', 'item' => 'Social link',
            'fields' => ['url' => ['Profile URL', 'url', 'required']],
        ],
    ];

    public const PROFILE = [
        'name' => ['Full name', 'text'], 'title' => ['Professional title', 'text'],
        'intro' => ['Short introduction', 'textarea'], 'objective' => ['Career objective', 'textarea'],
        'bio' => ['Biography', 'textarea'], 'interests' => ['Professional interests', 'textarea'],
        'email' => ['Public professional email', 'email'],
    ];

    public const SETTINGS = [
        'name' => ['Website name', 'text'], 'description' => ['SEO description', 'textarea'],
        'accent' => ['Accent color', 'color'],
    ];

    public const INQUIRY_TYPES = ['Project inquiry', 'Freelance opportunity', 'Employment', 'Collaboration', 'Other'];

    public const INQUIRY_STATUSES = ['New', 'Read', 'Replied', 'Resolved', 'Archived'];
}
