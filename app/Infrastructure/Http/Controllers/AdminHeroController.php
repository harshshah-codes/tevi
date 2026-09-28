<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Services\AdminHeroService;
use App\Domain\Models\HeroSlide;

final class AdminHeroController extends AdminController
{
    public function __construct(
        private readonly AdminHeroService $service,
    ) {
    }

    public function index(): void
    {
        $this->requireAuth();

        $slides = $this->service->getAllSlides();
        $flash = $this->getFlash();

        $rows = '';
        foreach ($slides as $slide) {
            $rows .= '<tr>'
                . '<td>' . $slide->id() . '</td>'
                . '<td><img src="' . htmlspecialchars($slide->imageUrl(), ENT_QUOTES) . '" alt="" style="width: 80px; height: 50px; object-fit: cover;"></td>'
                . '<td>' . htmlspecialchars($slide->tagline(), ENT_QUOTES) . '</td>'
                . '<td>' . htmlspecialchars($slide->headline(), ENT_QUOTES) . '</td>'
                . '<td>' . htmlspecialchars($slide->button(), ENT_QUOTES) . '</td>'
                . '<td>' . htmlspecialchars($slide->ctaLink(), ENT_QUOTES) . '</td>'
                . '<td class="text-end">'
                . '<a href="hero/edit?id=' . $slide->id() . '" class="btn btn-sm btn-outline-primary">Edit</a> '
                . '<form action="hero/delete" method="post" style="display:inline" onsubmit="return confirm(\'Delete this slide?\')">'
                . '<input type="hidden" name="csrf_token" value="' . $this->csToken() . '">'
                . '<input type="hidden" name="id" value="' . $slide->id() . '">'
                . '<button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>'
                . '</form>'
                . '</td>'
                . '</tr>';
        }

        $content = $this->render('hero/index.php', [
            'FLASH' => $flash ? '<div class="alert alert-' . $flash['type'] . '">' . htmlspecialchars($flash['message'], ENT_QUOTES) . '</div>' : '',
            'ROWS'  => $rows,
        ]);

        $this->output($content);
    }

    public function create(): void
    {
        $this->requireAuth();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();
            $slide = new HeroSlide(
                0,
                trim($_POST['tagline'] ?? ''),
                trim($_POST['headline'] ?? ''),
                trim($_POST['paragraph'] ?? ''),
                trim($_POST['button'] ?? ''),
                trim($_POST['cta_link'] ?? ''),
                trim($_POST['image_url'] ?? ''),
            );
            $this->service->createSlide($slide);
            $this->flash('Hero slide created successfully');
            $this->redirect('hero');
        }

        $content = $this->render('hero/form.php', [
            'TITLE'      => 'Create Hero Slide',
            'ACTION'     => 'hero/create',
            'CSRF_TOKEN' => $this->csToken(),
        ]);
        $this->output($content);
    }

    public function edit(): void
    {
        $this->requireAuth();
        $id = (int) ($_GET['id'] ?? 0);
        $slide = $this->service->getSlide($id);

        if (!$slide) {
            $this->flash('Slide not found', 'danger');
            $this->redirect('hero');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();
            $slide = new HeroSlide(
                $id,
                trim($_POST['tagline'] ?? ''),
                trim($_POST['headline'] ?? ''),
                trim($_POST['paragraph'] ?? ''),
                trim($_POST['button'] ?? ''),
                trim($_POST['cta_link'] ?? ''),
                trim($_POST['image_url'] ?? ''),
            );
            $this->service->updateSlide($slide);
            $this->flash('Hero slide updated successfully');
            $this->redirect('hero');
        }

        $content = $this->render('hero/form.php', [
            'TITLE'            => 'Edit Hero Slide',
            'ACTION'           => 'hero/edit?id=' . $id,
            'SLIDE'            => true,
            'SLIDE_TAGLINE'    => htmlspecialchars($slide->tagline(), ENT_QUOTES),
            'SLIDE_HEADLINE'   => htmlspecialchars($slide->headline(), ENT_QUOTES),
            'SLIDE_PARAGRAPH'  => htmlspecialchars($slide->paragraph(), ENT_QUOTES),
            'SLIDE_BUTTON'     => htmlspecialchars($slide->button(), ENT_QUOTES),
            'SLIDE_CTA_LINK'   => htmlspecialchars($slide->ctaLink(), ENT_QUOTES),
            'SLIDE_IMAGE_URL'  => htmlspecialchars($slide->imageUrl(), ENT_QUOTES),
            'CSRF_TOKEN'       => $this->csToken(),
        ]);
        $this->output($content);
    }

    public function delete(): void
    {
        $this->requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('hero');
        }
        $this->validateCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $this->service->deleteSlide($id);
        $this->flash('Hero slide deleted successfully');
        $this->redirect('hero');
    }

    private function output(string $content): void
    {
        $layout = $this->render('layout.php', [
            'TITLE'              => 'Hero Slides',
            'CONTENT'            => $content,
            'ACTIVE_HERO'        => 'active',
            'ACTIVE_DASHBOARD'   => '',
            'ACTIVE_PRODUCTS'    => '',
            'ACTIVE_CATEGORIES'  => '',
            'ACTIVE_REVIEWS'     => '',
        ]);
        header('Content-Type: text/html; charset=UTF-8');
        echo $layout;
        exit;
    }
}