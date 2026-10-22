<?php
declare(strict_types=1);

namespace Pixelite\Controllers;

use Pixelite\Customers;
use Pixelite\OrderRepository;
use Pixelite\Projects;
use Pixelite\Request;
use Pixelite\Response;

/** Customer records for administrators. Orders are linked to a customer only by an explicit administrator action – never by e-mail match. */
final class AdminCustomersController extends AdminBase
{
    public function index(Request $r): Response
    {
        return $this->guard($r) ?? $this->view('admin/customers', ['rows' => Customers::latest(200)]);
    }

    public function show(Request $r, array $p): Response
    {
        if ($g = $this->guard($r)) { return $g; }
        $c = Customers::find((int) $p['id']);
        if ($c === null) { return $this->notFound(); }
        unset($c['password_hash']);
        return $this->view('admin/customer', ['c' => $c, 'orders' => Customers::orders((int) $c['id']), 'projects' => Projects::forCustomer((int) $c['id'])]);
    }

    public function status(Request $r, array $p): Response
    {
        if ($g = $this->guardPost($r)) { return $g; }
        $c = Customers::find((int) $p['id']);
        if ($c === null) { return $this->notFound(); }
        $to = $r->post('status') === 'disabled' ? 'disabled' : 'active';
        Customers::setStatus((int) $c['id'], $to);
        return $this->redirect('/admin/customers/' . $c['id'], $to === 'disabled' ? 'Account disabled: the customer is signed out and cannot sign in.' : 'Account enabled.');
    }

    /** Link an order to a customer (or unlink with an empty e-mail). The administrator verifies the match; nothing is automatic. */
    public function linkOrder(Request $r, array $p): Response
    {
        if ($g = $this->guardPost($r)) { return $g; }
        if ((new OrderRepository())->find((int) $p['id']) === null) { return $this->notFound(); }
        $email = trim($r->post('customer_email'));
        if ($email === '') {
            Customers::linkOrder((int) $p['id'], 0);
            return $this->redirect('/admin/orders/' . (int) $p['id'], 'The order is no longer linked to a customer.');
        }
        $c = Customers::findByEmail($email);
        if ($c === null) {
            return $this->redirect('/admin/orders/' . (int) $p['id'], 'No customer account has that e-mail address.');
        }
        Customers::linkOrder((int) $p['id'], (int) $c['id']);
        return $this->redirect('/admin/orders/' . (int) $p['id'], 'Order linked to ' . $c['email'] . '.');
    }
}
