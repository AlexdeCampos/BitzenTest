<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $limit = $request->limit ?? 10;
        $page = $request->page ?? 1;
        $offset = (int) $limit * ((int) $page - 1);

        $companyQuery = DB::table('companies');
        $totalItems = $companyQuery->count();

        if ($request->search) {
            $search = "%$request->search%";
            $companyQuery->where('social_name', 'like', $search)
                ->orWhere('legal_name', 'like', $search);
        }

        $companyQuery->limit($limit);
        $companyQuery->offset($offset);



        $searchResult = $companyQuery->get(
            [
                "id",
                "document_number",
                "social_name",
                "legal_name",
                "creation_date",
                "responsible_email",
                "responsible_name",

            ]
        );
        return $this->buildPagination($page, $limit, $offset, $totalItems, $searchResult);
    }

    public function show(string $id)
    {
        $company = Company::where('id', $id)->first();
        if (!$company) {
            return response()->json([
                'errors' => true,
                'message' => 'Empresa não encontrada.',
            ], 404);
        }
        return $company;
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'document_number' => 'required',
            'social_name' => 'required',
            'legal_name' => 'required',
            'creation_date' => 'required|date',
            'responsible_email' => 'required|email',
            'responsible_name' => 'required',
        ]);


        if ($validator->fails()) {
            return response()->json([
                'errors' => true,
                'message' => 'Dados incompletos ou errados, por favor verifique',
            ], 422);
        }

        $validated = $validator->validated();

        $hasDuplicatedDocument = $this->verifyDuplicatedDocument($validated["document_number"]);
        if ($hasDuplicatedDocument) {
            return response()->json([
                'errors' => true,
                'message' => 'Já existe uma empresa registrada com o documento enviado.',
            ], 422);
        }

        $companyData = $this->getCompanyData($validated['document_number']);

        if (isset($companyData["type"])) {
            return response()->json([
                'errors' => true,
                'message' => 'Não foi possível validar os dados da empresa, verifique se o CNPJ enviado está correto',
            ], 404);
        }

        $hasValidCnae = $this->validateCompanyData($companyData);
        if (!$hasValidCnae) {
            return response()->json([
                'errors' => true,
                'message' => 'Empresa não se possuí ao cnae necessário',
            ], 404);
        }

        return $this->createCompany($validated);
    }

    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'document_number' => 'required',
            'social_name' => 'required',
            'legal_name' => 'required',
            'creation_date' => 'required|date',
            'responsible_email' => 'required|email',
            'responsible_name' => 'required',
        ]);


        if ($validator->fails()) {
            return response()->json([
                'errors' => true,
                'message' => 'Dados incompletos ou errados, por favor verifique.',
            ], 422);
        }
        $validated = $validator->validated();

        $oldCompany = Company::where('id', $id)->first(
            [
                "document_number",
                "social_name",
                "legal_name",
                "creation_date",
                "responsible_email",
                "responsible_name",
            ]
        );
        if (!$oldCompany) {
            return response()->json([
                'errors' => true,
                'message' => 'Empresa não encontrada.',
            ], 404);
        }

        $cleanDocument = $this->clearNoNNumbers($validated["document_number"]);
        if ($oldCompany->document_number != $cleanDocument) {
            $hasDuplicatedDocument = $this->verifyDuplicatedDocument($cleanDocument);
            if ($hasDuplicatedDocument) {
                return response()->json([
                    'errors' => true,
                    'message' => 'Já existe uma empresa registrada com o documento enviado.',
                ], 422);
            }

            $companyData = $this->getCompanyData($validated['document_number']);
            if (isset($companyData["type"])) {
                return response()->json([
                    'errors' => true,
                    'message' => 'Não foi possível validar os dados da empresa, verifique se o CNPJ enviado está correto.',
                ], 404);
            }


            $hasValidCnae = $this->validateCompanyData($companyData);
            if (!$hasValidCnae) {
                return response()->json([
                    'errors' => true,
                    'message' => 'Empresa não se possuí ao cnae necessário.',
                ], 404);
            }
        }

        return $this->updateCompany($validated, $id);
    }

    public function destroy(string $id)
    {
        $company = Company::where('id', $id)->first();
        if (!$company) {
            return response()->json([
                'errors' => true,
                'message' => 'Empresa não encontrada.',
            ], 404);
        }

        $company->delete();
        return;
    }

    private function getCompanyData(string $documentNumber)
    {
        $clearDocumentNumber = $this->clearNoNNumbers($documentNumber);
        $response = Http::acceptJson()->get("https://brasilapi.com.br/api/cnpj/v1/$clearDocumentNumber");
        return $response;
    }

    private function validateCompanyData($company)
    {
        $primary_cnae = $this->clearNoNNumbers($company["cnae_fiscal"]);
        $secondary_cnae = $company["cnaes_secundarios"];

        $hasValidCnae = false;
        if ($primary_cnae == 6202300) {
            $hasValidCnae = true;
        }

        foreach ($secondary_cnae as $cnae) {
            $cleanCnae = $this->clearNoNNumbers($cnae['codigo']);
            if ($cleanCnae == 6202300) {
                $hasValidCnae = true;
            }
        }

        return $hasValidCnae;
    }

    private function createCompany($company)
    {
        $newCompany = new Company;

        $newCompany->document_number = $this->clearNoNNumbers($company["document_number"]);
        $newCompany->social_name = $company["social_name"];
        $newCompany->legal_name = $company["legal_name"];
        $newCompany->creation_date = $company["creation_date"];
        $newCompany->responsible_email = $company["responsible_email"];
        $newCompany->responsible_name = $company["responsible_name"];
        $newCompany->save();

        return $newCompany;
    }

    private function verifyDuplicatedDocument(string $documentNumber)
    {
        $cleanDocument = $this->clearNoNNumbers($documentNumber);
        $duplicatedCompany = Company::where('document_number', $cleanDocument)->first();
        return (bool) $duplicatedCompany;
    }

    private function updateCompany($newCompanyData, string $id)
    {
        $cleanDocument = $this->clearNoNNumbers($newCompanyData["document_number"]);
        Company::where('id', $id)->update([
            "document_number" => $cleanDocument,
            "social_name" => $newCompanyData["social_name"],
            "legal_name" => $newCompanyData["legal_name"],
            "creation_date" => $newCompanyData["creation_date"],
            "responsible_email" => $newCompanyData["responsible_email"],
            "responsible_name" => $newCompanyData["responsible_name"],
        ]);
        return $newCompanyData;
    }

    private function buildPagination($page, $limit, $offset, $total, $result)
    {
        $firstItem = $offset + 1;
        $lastItem = ($offset + $limit) < $total ? $offset + $limit : $total;
        $totalPages = ($total / $limit) >= 1 ?  ceil($total / $limit) : 1;
        $pagination = [
            "page" => $page,
            "total_pages" => $totalPages,
            "curren_view" => "$firstItem à $lastItem de $total",
            "items" => $result
        ];

        return $pagination;
    }

    private function clearNoNNumbers(string $str)
    {
        return preg_replace('~\D~', '', $str);
    }
}
